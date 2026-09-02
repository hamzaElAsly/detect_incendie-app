"""Build and validate the fire/smoke Dataset V2 without touching raw data or V1.

The command line entry point is deliberately self-contained and deterministic:

    python scripts/dataset_v2/pipeline.py run

It discovers Roboflow sources below ``fire_factory_dataset_v2_raw``, records a
complete inventory, performs exact/name/perceptual deduplication, applies the
explicit mapping in ``mapping.yaml``, creates grouped 75/15/10 splits, and
writes the requested reports.  A non-empty output is never deleted; a later
run gets a timestamped sibling unless ``--output`` points to a new directory.
No training code is imported or executed.
"""
from __future__ import annotations

import argparse
import csv
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor
from dataclasses import dataclass, field
from datetime import datetime, timezone
import hashlib
import json
import math
from pathlib import Path
import random
import shutil
import sys
from typing import Iterable

try:
    import yaml
except ImportError as exc:  # pragma: no cover - environment diagnostic
    raise SystemExit("PyYAML is required: pip install pyyaml") from exc

try:
    from PIL import Image
except ImportError as exc:  # pragma: no cover - environment diagnostic
    raise SystemExit("Pillow is required: pip install pillow") from exc


ROOT = Path(__file__).resolve().parents[2]
RAW_ROOT = ROOT / "fire_factory_dataset_v2_raw"
V1_ROOT = ROOT / "fire_factory_dataset_clean_20260823"
DEFAULT_OUTPUT = ROOT / "fire_factory_dataset_v2"
MAPPING_PATH = Path(__file__).with_name("mapping.yaml")
IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}
SPLIT_ALIASES = {"train": "train", "valid": "val", "val": "val", "test": "test"}
FINAL_NAMES = {0: "fire", 1: "smoke"}
SIZE_BUCKETS = ("very_small", "small", "medium", "large")
SEED = 20260827

INVENTORY_FIELDS = [
    "source", "original_split", "image", "label", "image_relpath", "label_relpath",
    "width", "height", "image_format", "image_bytes", "image_sha256", "perceptual_hash",
    "image_status", "label_status", "classes", "class_ids", "instance_count",
    "boxes", "missing_label", "label_without_image", "negative_candidate", "notes",
]
DEDUP_FIELDS = ["dedup_group", "dedup_type", "kept", "source", "original_split", "image", "sha256", "perceptual_hash", "reason"]
ISSUE_FIELDS = ["stage", "source", "split", "image", "label", "line", "issue_type", "severity", "details", "action"]
FINAL_FIELDS = [
    "split", "images", "negative_images", "images_with_fire", "images_with_smoke",
    "fire_instances", "smoke_instances", "fire_very_small", "fire_small", "fire_medium", "fire_large",
    "smoke_very_small", "smoke_small", "smoke_medium", "smoke_large", "corrupt_images", "invalid_labels",
    "images_without_labels", "labels_without_images", "exact_inter_split_duplicates", "near_inter_split_duplicates",
]


@dataclass
class Record:
    source: str
    original_split: str
    image: Path
    label: Path | None
    source_names: dict[int, str]
    width: int = 0
    height: int = 0
    image_format: str = ""
    image_status: str = "unknown"
    label_status: str = "unknown"
    sha256: str = ""
    perceptual_hash: str = ""
    raw_lines: list[dict] = field(default_factory=list)
    issues: list[str] = field(default_factory=list)
    decision: str = ""
    final_lines: list[str] = field(default_factory=list)
    final_classes: list[int] = field(default_factory=list)
    notes: list[str] = field(default_factory=list)
    group_id: str = ""

    @property
    def image_key(self) -> str:
        return f"{self.source}/{self.original_split}/{self.image.name}"

    @property
    def is_negative(self) -> bool:
        return self.decision == "accepted_negative"


def write_csv(path: Path, fields: list[str], rows: Iterable[dict]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)


def safe_name_part(value: str, limit: int) -> str:
    cleaned = "".join(char if char.isalnum() or char in "-_." else "_" for char in value)
    return cleaned[:limit].strip("._") or "asset"


def prepared_image_name(record: Record, prefix: str = "asset") -> str:
    asset_id = hashlib.sha256(record.image_key.encode()).hexdigest()[:12]
    source = safe_name_part(record.source, 32)
    stem = safe_name_part(record.image.stem, 64)
    suffix = safe_name_part(record.image.suffix.lower(), 8) or ".jpg"
    if not suffix.startswith("."):
        suffix = "." + suffix
    return f"{prefix}_{asset_id}__{source}__{stem}{suffix}"


def read_csv(path: Path) -> list[dict[str, str]]:
    if not path.exists():
        return []
    with path.open(encoding="utf-8-sig", newline="") as stream:
        return list(csv.DictReader(stream))


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def average_hash(image: Image.Image) -> str:
    """Return a deterministic 64-bit average hash from an already-open image."""
    image = image.convert("L").resize((8, 8), Image.Resampling.BILINEAR)
    pixels = list(image.getdata())
    average = sum(pixels) / len(pixels)
    return "".join("1" if value >= average else "0" for value in pixels)


def perceptual_hash(path: Path) -> str:
    """Return a deterministic 64-bit average hash using Pillow only."""
    with Image.open(path) as image:
        image.load()
        return average_hash(image)


def hamming(left: str, right: str) -> int:
    return sum(a != b for a, b in zip(left, right))


def yaml_names(path: Path) -> dict[int, str]:
    payload = yaml.safe_load(path.read_text(encoding="utf-8")) or {}
    names = payload.get("names", {})
    if isinstance(names, list):
        return {index: str(name) for index, name in enumerate(names)}
    return {int(key): str(value) for key, value in names.items()}


def discover_sources(raw_root: Path) -> list[tuple[Path, dict[int, str]]]:
    sources = []
    for directory in sorted(p for p in raw_root.iterdir() if p.is_dir()):
        yaml_path = directory / "data.yaml"
        if not yaml_path.exists():
            raise ValueError(f"Source sans data.yaml: {directory}")
        names = yaml_names(yaml_path)
        if not names:
            raise ValueError(f"data.yaml sans classes: {yaml_path}")
        sources.append((directory, names))
    if len(sources) != 2:
        raise ValueError(f"Deux sources attendues sous {raw_root}, trouvees: {len(sources)}")
    return sources


def parse_yolo(path: Path, names: dict[int, str]) -> tuple[list[dict], list[str]]:
    annotations, issues = [], []
    try:
        content = path.read_text(encoding="utf-8-sig", errors="replace")
    except OSError as exc:
        return [], [f"lecture label impossible: {exc}"]
    for line_number, raw in enumerate(content.splitlines(), 1):
        if not raw.strip():
            continue
        parts = raw.split()
        if len(parts) != 5:
            issues.append(f"ligne {line_number}: exactement 5 champs attendus, {len(parts)} recus")
            continue
        try:
            class_id = int(parts[0])
            values = [float(value) for value in parts[1:]]
        except ValueError:
            issues.append(f"ligne {line_number}: class ID ou coordonnee non numerique")
            continue
        x, y, width, height = values
        if class_id not in names:
            issues.append(f"ligne {line_number}: class ID {class_id} absent du YAML")
        if not all(math.isfinite(value) for value in values):
            issues.append(f"ligne {line_number}: valeur non finie")
        elif not all(0.0 <= value <= 1.0 for value in values):
            issues.append(f"ligne {line_number}: coordonnee non normalisee")
        elif width <= 0 or height <= 0:
            issues.append(f"ligne {line_number}: width/height doit etre > 0")
        elif x - width / 2 < -1e-9 or y - height / 2 < -1e-9 or x + width / 2 > 1 + 1e-9 or y + height / 2 > 1 + 1e-9:
            issues.append(f"ligne {line_number}: bbox hors image")
        annotations.append({"line": line_number, "class_id": class_id, "name": names.get(class_id, "unknown"), "x": x, "y": y, "width": width, "height": height})
    return annotations, issues


def image_and_label_files(split_dir: Path) -> tuple[list[Path], list[Path]]:
    images_dir, labels_dir = split_dir / "images", split_dir / "labels"
    images = sorted(p for p in images_dir.rglob("*") if p.is_file() and p.suffix.lower() in IMAGE_EXTENSIONS) if images_dir.exists() else []
    labels = sorted(p for p in labels_dir.rglob("*.txt")) if labels_dir.exists() else []
    return images, labels


def inspect_image(path: Path) -> dict:
    """Decode, validate, hash, and measure one image in a worker thread."""
    try:
        with Image.open(path) as opened:
            opened.load()
            width, height = opened.width, opened.height
            image_format = opened.format or path.suffix.upper().lstrip(".")
            image_hash = average_hash(opened)
        return {"status": "valid", "width": width, "height": height, "format": image_format, "phash": image_hash, "sha256": sha256(path), "error": ""}
    except Exception as exc:
        return {"status": "invalid", "width": 0, "height": 0, "format": "", "phash": "", "sha256": "", "error": f"image illisible: {exc}"}


def is_valid_image(path: Path) -> bool:
    try:
        with Image.open(path) as opened:
            opened.verify()
        return True
    except Exception:
        return False


def inventory_sources(raw_root: Path, reports: Path) -> list[Record]:
    records: list[Record] = []
    for source_dir, names in discover_sources(raw_root):
        for split_dir in sorted(p for p in source_dir.iterdir() if p.is_dir() and p.name in SPLIT_ALIASES):
            split = SPLIT_ALIASES[split_dir.name]
            images, labels = image_and_label_files(split_dir)
            by_stem: dict[str, list[Path]] = defaultdict(list)
            for label in labels:
                by_stem[label.stem].append(label)
            image_stems = {image.stem for image in images}
            with ThreadPoolExecutor(max_workers=8) as executor:
                image_details = dict(zip(images, executor.map(inspect_image, images)))
            for image in images:
                record = Record(source_dir.name, split, image, None, names)
                matches = by_stem.get(image.stem, [])
                record.label = matches[0] if matches else None
                if len(matches) > 1:
                    record.issues.append("plusieurs labels pour le meme stem")
                detail = image_details[image]
                record.width, record.height, record.image_format = detail["width"], detail["height"], detail["format"]
                record.image_status, record.perceptual_hash, record.sha256 = detail["status"], detail["phash"], detail["sha256"]
                if detail["error"]:
                    record.issues.append(detail["error"])
                if record.label is None:
                    record.label_status = "missing"
                    record.issues.append("label absent")
                else:
                    record.raw_lines, label_issues = parse_yolo(record.label, names)
                    record.label_status = "invalid" if label_issues else "valid"
                    record.issues.extend(label_issues)
                records.append(record)
            for label in labels:
                if label.stem not in image_stems:
                    record = Record(source_dir.name, split, split_dir / "images" / (label.stem + ".missing"), label, names)
                    record.label_status = "label_without_image"
                    record.issues.append("label sans image correspondante")
                    record.raw_lines, label_issues = parse_yolo(label, names)
                    record.issues.extend(label_issues)
                    records.append(record)
    rows = []
    for record in records:
        rows.append({
            "source": record.source, "original_split": record.original_split, "image": record.image.name,
            "label": record.label.name if record.label else "", "image_relpath": record.image.relative_to(raw_root).as_posix() if record.image.exists() else "",
            "label_relpath": record.label.relative_to(raw_root).as_posix() if record.label and record.label.exists() else "",
            "width": record.width, "height": record.height, "image_format": record.image_format, "image_bytes": record.image.stat().st_size if record.image.exists() else 0,
            "image_sha256": record.sha256, "perceptual_hash": record.perceptual_hash, "image_status": record.image_status,
            "label_status": record.label_status, "classes": ";".join(sorted({a["name"] for a in record.raw_lines})),
            "class_ids": ";".join(str(x) for x in sorted({a["class_id"] for a in record.raw_lines})),
            "instance_count": len(record.raw_lines), "boxes": json.dumps([{k: a[k] for k in ("class_id", "x", "y", "width", "height")} for a in record.raw_lines], separators=(",", ":")),
            "missing_label": record.label_status == "missing", "label_without_image": record.label_status == "label_without_image",
            "negative_candidate": not record.raw_lines and record.label_status == "valid", "notes": " | ".join(record.issues),
        })
    write_csv(reports / "v2_source_inventory.csv", INVENTORY_FIELDS, rows)
    by_source = Counter(record.source for record in records)
    by_split = Counter((record.source, record.original_split) for record in records)
    markdown = ["# V2 source inventory", "", f"Generated UTC: {datetime.now(timezone.utc).isoformat()}", "", "## Sources and YAML classes", ""]
    for source_dir, names in discover_sources(raw_root):
        markdown.append(f"- `{source_dir.name}`: " + ", ".join(f"{key} = {value}" for key, value in names.items()))
    markdown += ["", "## Counts", "", "| source | split | records | valid images | valid labels | instances |", "|---|---:|---:|---:|---:|---:|"]
    for (source, split), count in sorted(by_split.items()):
        selected = [r for r in records if r.source == source and r.original_split == split]
        markdown.append(f"| {source} | {split} | {count} | {sum(r.image_status == 'valid' for r in selected)} | {sum(r.label_status == 'valid' for r in selected)} | {sum(len(r.raw_lines) for r in selected)} |")
    markdown += ["", f"Total records including orphan labels: {len(records)}", f"Images with no label: {sum(r.label_status == 'missing' for r in records)}", f"Labels with no image: {sum(r.label_status == 'label_without_image' for r in records)}", f"Invalid images: {sum(r.image_status == 'invalid' for r in records)}", "", "The CSV is the complete per-file inventory; no source file was modified."]
    (reports / "V2_SOURCE_INVENTORY.md").write_text("\n".join(markdown) + "\n", encoding="utf-8")
    return records


def records_from_inventory(raw_root: Path, reports: Path) -> list[Record]:
    """Resume after an interrupted run using the completed inventory CSV."""
    source_names = {directory.name: names for directory, names in discover_sources(raw_root)}
    records = []
    for row in read_csv(reports / "v2_source_inventory.csv"):
        source = row["source"]
        image = raw_root / row["image_relpath"] if row["image_relpath"] else raw_root / source / row["original_split"] / "images" / row["image"]
        label = raw_root / row["label_relpath"] if row["label_relpath"] else None
        record = Record(source, row["original_split"], image, label, source_names[source])
        record.width = int(row["width"] or 0); record.height = int(row["height"] or 0)
        record.image_format = row["image_format"]; record.sha256 = row["image_sha256"]; record.perceptual_hash = row["perceptual_hash"]
        record.image_status = row["image_status"]; record.label_status = row["label_status"]
        record.issues = [item.strip() for item in row["notes"].split("|") if item.strip()]
        for line_number, box in enumerate(json.loads(row.get("boxes") or "[]"), 1):
            class_id = int(box["class_id"])
            record.raw_lines.append({
                "line": line_number,
                "class_id": class_id,
                "name": record.source_names.get(class_id, "unknown"),
                "x": float(box["x"]),
                "y": float(box["y"]),
                "width": float(box["width"]),
                "height": float(box["height"]),
            })
        records.append(record)
    if not records:
        raise ValueError(f"Inventory cache empty: {reports / 'v2_source_inventory.csv'}")
    return records


def deduplicate(records: list[Record], reports: Path) -> tuple[list[Record], list[dict]]:
    usable = [r for r in records if r.image_status == "valid" and r.label_status != "label_without_image"]
    groups: list[list[Record]] = []
    by_sha: dict[str, list[Record]] = defaultdict(list)
    by_name: dict[str, list[Record]] = defaultdict(list)
    for record in usable:
        by_sha[record.sha256].append(record)
        by_name[record.image.name.lower()].append(record)
    exact_groups = [group for group in by_sha.values() if len(group) > 1]
    used: set[str] = set()
    for group in exact_groups:
        group = sorted(group, key=lambda r: r.image_key)
        groups.append(group)
        used.update(r.image_key for r in group)
    # Exact average-hash groups are a bounded perceptual-dedup pass.  A
    # Hamming-neighbour search is intentionally not attempted: large corpora
    # contain broad low-information buckets (black/white industrial frames),
    # and comparing every member would become quadratic and over-remove valid
    # images.  SHA-256 remains the authoritative exact-byte test.
    buckets: dict[str, list[Record]] = defaultdict(list)
    for record in usable:
        if record.image_key in used:
            continue
        buckets[record.perceptual_hash].append(record)
    for bucket in buckets.values():
        bucket = sorted(bucket, key=lambda r: r.image_key)
        near_groups: list[list[Record]] = []
        for record in bucket:
            match = next((group for group in near_groups if record.perceptual_hash == group[0].perceptual_hash), None)
            if match:
                match.append(record)
            else:
                near_groups.append([record])
        groups.extend(group for group in near_groups if len(group) > 1)
    rows: list[dict] = []
    kept_keys: set[str] = set()
    group_index = 0
    for group in groups:
        dedup_type = "exact_sha256" if len({r.sha256 for r in group}) == 1 else "near_perceptual_hash"
        group_index += 1
        keep = sorted(group, key=lambda r: (r.source, r.original_split, r.image.name))[0]
        kept_keys.add(keep.image_key)
        for record in group:
            rows.append({"dedup_group": f"D{group_index:05d}", "dedup_type": dedup_type, "kept": record is keep, "source": record.source, "original_split": record.original_split, "image": record.image.name, "sha256": record.sha256, "perceptual_hash": record.perceptual_hash, "reason": "deterministic representative" if record is keep else "duplicate removed before split"})
    row_keys = {row["source"] + "/" + row["original_split"] + "/" + row["image"] for row in rows}
    for record in usable:
        if record.image_key not in row_keys:
            rows.append({"dedup_group": "", "dedup_type": "unique", "kept": True, "source": record.source, "original_split": record.original_split, "image": record.image.name, "sha256": record.sha256, "perceptual_hash": record.perceptual_hash, "reason": "unique"})
            kept_keys.add(record.image_key)
    write_csv(reports / "v2_duplicates.csv", DEDUP_FIELDS, rows)
    kept = [
        record for record in records
        if record.image_key in kept_keys
        or record.image_status != "valid"
        or record.label_status == "label_without_image"
    ]
    # Orphan labels and corrupt/missing-label files are retained in the audit but never built.
    report = ["# V2 deduplication report", "", f"SHA-256 duplicate groups: {sum(r['dedup_type'] == 'exact_sha256' for r in rows if r['dedup_group'])}", f"Perceptual duplicate groups: {sum(r['dedup_type'] == 'near_perceptual_hash' for r in rows if r['dedup_group'])}", f"Exact/name/near duplicate records removed: {len(usable) - len(kept)}", "", "## Policy", "", "Exact SHA-256 matches are removed first. Original basename collisions are recorded in the CSV even when the bytes differ. Perceptual duplicates use exact 64-bit average-hash equality, a bounded conservative test; a broad Hamming-neighbour search is not used because it over-groups low-information industrial frames. The representative is selected by source/split/name, so reruns are deterministic.", ""]
    for name, group in sorted(by_name.items()):
        if len(group) > 1:
            report.append(f"- Name collision `{name}`: " + ", ".join(r.image_key for r in group))
    (reports / "V2_DEDUPLICATION_REPORT.md").write_text("\n".join(report) + "\n", encoding="utf-8")
    return kept, rows


def load_mapping(path: Path) -> dict:
    return yaml.safe_load(path.read_text(encoding="utf-8"))


def mapping_for(mapping: dict, source: str, class_id: int, name: str) -> dict:
    source_map = mapping.get("sources", {}).get(source, {})
    configured = source_map.get(str(class_id)) or source_map.get(class_id)
    if configured is None:
        return {"decision": "manual_review", "final_class": None, "reason": "class ID absent de la configuration explicite"}
    if configured.get("name", "").strip().lower() != name.strip().lower():
        raise ValueError(f"Mapping YAML mismatch for {source} class {class_id}: {name} != {configured.get('name')}")
    return configured


def apply_mapping(records: list[Record], mapping: dict, reports: Path, output: Path) -> tuple[list[Record], list[Record], list[dict]]:
    accepted, review, mapping_rows = [], [], []
    for source, entries in mapping.get("sources", {}).items():
        for class_id, config in entries.items():
            mapping_rows.append({"source": source, "source_class_id": class_id, "source_class": config.get("name", ""), "decision": config.get("decision", ""), "final_class": config.get("final_class", ""), "reason": config.get("reason", "")})
    for record in records:
        if record.image_status != "valid" or record.label_status in {"missing", "label_without_image", "invalid"}:
            record.decision = "manual_review"
            review.append(record)
            continue
        decisions = [mapping_for(mapping, record.source, a["class_id"], a["name"]) for a in record.raw_lines]
        if not record.raw_lines:
            # Empty labels are not trusted as negatives without an explicit source policy.
            policy = mapping.get("empty_label_policy", {}).get(record.source, "manual_review")
            record.decision = "accepted_negative" if policy == "hard_negative" else "manual_review"
            (accepted if record.decision == "accepted_negative" else review).append(record)
            continue
        if any(d.get("decision") == "manual_review" for d in decisions):
            record.decision = "manual_review"
            review.append(record)
            continue
        if any(d.get("decision") == "rejected" for d in decisions):
            record.decision = "rejected"
            review.append(record)
            continue
        if all(d.get("decision") == "hard_negative" for d in decisions):
            record.decision = "accepted_negative"
            accepted.append(record)
            continue
        if any(d.get("decision") != "map" for d in decisions):
            record.decision = "manual_review"
            review.append(record)
            continue
        for annotation, decision in zip(record.raw_lines, decisions):
            final_class = int(decision["final_class"])
            record.final_classes.append(final_class)
            record.final_lines.append(f"{final_class} {annotation['x']:.10g} {annotation['y']:.10g} {annotation['width']:.10g} {annotation['height']:.10g}")
        record.decision = "accepted_positive"
        accepted.append(record)
    write_csv(reports / "v2_class_mapping.csv", ["source", "source_class_id", "source_class", "decision", "final_class", "reason"], mapping_rows)
    lines = ["# V2 class mapping report", "", "Final classes are exactly `0 = fire` and `1 = smoke`.", "", "| source | source ID | source class | decision | final class | reason |", "|---|---:|---|---|---:|---|"]
    for row in mapping_rows:
        lines.append(f"| {row['source']} | {row['source_class_id']} | {row['source_class']} | {row['decision']} | {row['final_class']} | {row['reason']} |")
    lines += ["", f"Accepted positive images: {sum(r.decision == 'accepted_positive' for r in accepted)}", f"Accepted hard-negative images: {sum(r.decision == 'accepted_negative' for r in accepted)}", f"Manual-review images: {len(review)}", "", "No class was inferred from an ID alone: every ID/name pair is checked against the source YAML and mapping.yaml."]
    (reports / "V2_CLASS_MAPPING_REPORT.md").write_text("\n".join(lines) + "\n", encoding="utf-8")
    return accepted, review, mapping_rows


def bucket(area: float) -> str:
    if area < 0.01: return "very_small"
    if area < 0.03: return "small"
    if area < 0.10: return "medium"
    return "large"


def smoke_analysis(accepted: list[Record], reports: Path) -> None:
    rows = []
    for source in sorted({r.source for r in accepted} | {"V1_reference"}):
        source_records = [r for r in accepted if r.source == source] if source != "V1_reference" else []
        for split in ("train", "val", "test"):
            selected = [r for r in source_records if r.original_split == split] if source != "V1_reference" else []
            counts = Counter(
                bucket(annotation["width"] * annotation["height"])
                for record in selected
                for annotation, final_class in zip(record.raw_lines, record.final_classes)
                if record.decision == "accepted_positive" and final_class == 1
            )
            rows.append({"dataset": "V2_candidate" if source != "V1_reference" else "V1_reference", "source": source, "split": split, **{f"smoke_{key}": counts[key] for key in SIZE_BUCKETS}, "smoke_instances": sum(counts.values())})
    # V1 is read-only and used solely as a comparison baseline.
    for split in ("train", "val", "test"):
        counts = Counter()
        label_dir = V1_ROOT / "labels" / split
        if label_dir.exists():
            labels = sorted(label_dir.glob("*.txt"))
            with ThreadPoolExecutor(max_workers=8) as executor:
                for label_counts in executor.map(smoke_counts_from_label, labels):
                    counts.update(label_counts)
        for row in rows:
            if row["dataset"] == "V1_reference" and row["split"] == split:
                row.update({f"smoke_{key}": counts[key] for key in SIZE_BUCKETS}, smoke_instances=sum(counts.values()))
    write_csv(reports / "v2_smoke_size_distribution.csv", ["dataset", "source", "split", "smoke_very_small", "smoke_small", "smoke_medium", "smoke_large", "smoke_instances"], rows)
    lines = ["# V2 smoke size analysis", "", "YOLO normalized area thresholds: very_small < 0.01; small < 0.03; medium < 0.10; large >= 0.10.", "", "| dataset | source | split | very_small | small | medium | large | total |", "|---|---|---|---:|---:|---:|---:|---:|"]
    for row in rows:
        lines.append(f"| {row['dataset']} | {row['source']} | {row['split']} | {row['smoke_very_small']} | {row['smoke_small']} | {row['smoke_medium']} | {row['smoke_large']} | {row['smoke_instances']} |")
    lines += ["", "V2 uses each accepted source image at most once and stratifies groups using smoke size, class presence, source, and positive/negative status. It does not mass-duplicate small-smoke images.", ""]
    (reports / "V2_SMOKE_SIZE_ANALYSIS.md").write_text("\n".join(lines), encoding="utf-8")


def smoke_counts_from_label(label: Path) -> Counter:
    counts = Counter()
    for raw in label.read_text(encoding="utf-8-sig", errors="replace").splitlines():
        parts = raw.split()
        if len(parts) == 5 and parts[0].isdigit() and int(parts[0]) == 1:
            try: counts[bucket(float(parts[3]) * float(parts[4]))] += 1
            except ValueError: pass
    return counts


def annotation_audit(records: list[Record], review: list[Record], reports: Path, output: Path) -> list[dict]:
    issues = []
    for record in records:
        for issue in record.issues:
            issues.append({"stage": "source", "source": record.source, "split": record.original_split, "image": record.image.name, "label": record.label.name if record.label else "", "line": "", "issue_type": "source_integrity", "severity": "error", "details": issue, "action": "manual_review/exclude"})
        for annotation in record.raw_lines:
            area = annotation["width"] * annotation["height"]
            if area < 0.0001:
                issues.append({"stage": "source", "source": record.source, "split": record.original_split, "image": record.image.name, "label": record.label.name if record.label else "", "line": annotation["line"], "issue_type": "extremely_small_bbox", "severity": "warning", "details": f"area={area:.8f}", "action": "manual_review"})
            if area >= 0.95:
                issues.append({"stage": "source", "source": record.source, "split": record.original_split, "image": record.image.name, "label": record.label.name if record.label else "", "line": annotation["line"], "issue_type": "near_full_image_bbox", "severity": "warning", "details": f"area={area:.8f}", "action": "manual_review"})
        if len(record.raw_lines) > 50:
            issues.append({"stage": "source", "source": record.source, "split": record.original_split, "image": record.image.name, "label": record.label.name if record.label else "", "line": "", "issue_type": "many_boxes", "severity": "warning", "details": str(len(record.raw_lines)), "action": "manual_review"})
    write_csv(reports / "v2_annotation_issues.csv", ISSUE_FIELDS, issues)
    lines = ["# V2 annotation audit", "", "Every source label is parsed against its own YAML class IDs before mapping. Invalid files, orphan labels, ambiguous classes, and suspicious boxes are not silently corrected.", "", f"Source integrity/syntax issues: {sum(i['issue_type'] == 'source_integrity' for i in issues)}", f"Suspicious-box warnings: {sum(i['issue_type'] != 'source_integrity' for i in issues)}", f"Images sent to manual review: {len(review)}", "", "## Manual review policy", "", "Review material is copied below `manual_review/` with a manifest. No ambiguous annotation is auto-corrected or converted to fire/smoke.", ""]
    (reports / "V2_ANNOTATION_AUDIT.md").write_text("\n".join(lines), encoding="utf-8")
    return issues


def copy_manual_review(review: list[Record], output: Path) -> None:
    image_dir, label_dir = output / "manual_review" / "images", output / "manual_review" / "labels"
    image_dir.mkdir(parents=True, exist_ok=True); label_dir.mkdir(parents=True, exist_ok=True)
    rows = []
    for index, record in enumerate(sorted(review, key=lambda r: r.image_key), 1):
        if not record.image.exists():
            continue
        name = prepared_image_name(record, f"review_{index:06d}")
        shutil.copy2(record.image, image_dir / name)
        if record.label and record.label.exists():
            shutil.copy2(record.label, label_dir / (Path(name).stem + ".txt"))
        rows.append({"review_file": name, "source": record.source, "original_split": record.original_split, "original_image": record.image.name, "original_label": record.label.name if record.label else "", "reason": " | ".join(record.issues) or "ambiguous/unmapped class or unverified empty label", "source_classes": ";".join(sorted({a['name'] for a in record.raw_lines})), "action": "manual_review"})
    write_csv(output / "manual_review" / "manifest.csv", ["review_file", "source", "original_split", "original_image", "original_label", "reason", "source_classes", "action"], rows)


def hard_negative_report(accepted: list[Record], reports: Path) -> None:
    negatives = [r for r in accepted if r.is_negative]
    lines = ["# V2 hard negatives report", "", "Hard negatives are retained only when the explicit mapping marks all source annotations as `hard_negative`. Empty labels are not accepted as negatives by default, because an empty annotation alone cannot prove absence of smoke.", "", f"Retained hard-negative images: {len(negatives)}", ""]
    lines.extend(f"- `{r.source}/{r.original_split}/{r.image.name}`: classes={','.join(a['name'] for a in r.raw_lines) or 'empty label'}" for r in negatives)
    lines += ["", "Images with `Bat lua`, `Candle`, invalid labels, missing labels, or other ambiguous content remain in manual_review.", ""]
    (reports / "V2_HARD_NEGATIVES_REPORT.md").write_text("\n".join(lines), encoding="utf-8")


def source_group(record: Record) -> str:
    stem = record.image.stem
    if ".rf." in stem:
        stem = stem.split(".rf.", 1)[0]
    return f"{record.source}::{stem}"


def make_groups(records: list[Record]) -> dict[str, list[Record]]:
    """Group sequence-like filenames after dedup has removed hash collisions.

    Deduplication has already removed every near-hash pair inside its bounded
    hash buckets.  Repeating an all-record hash comparison here would be
    quadratic and provides no additional leakage protection.  The remaining
    deterministic grouping strips Roboflow's export hash, so frames sharing an
    original basename stay together.
    """
    groups: dict[str, list[Record]] = defaultdict(list)
    for record in records:
        record.group_id = source_group(record)
        groups[record.group_id].append(record)
    return dict(groups)


def feature_vector(records: list[Record]) -> Counter:
    vector = Counter(total=1, fire_images=0, smoke_images=0, negative_images=0, fire_instances=0, smoke_instances=0)
    for record in records:
        classes = set(record.final_classes)
        vector["fire_images"] += int(0 in classes)
        vector["smoke_images"] += int(1 in classes)
        vector["negative_images"] += int(record.is_negative)
        vector["fire_instances"] += sum(c == 0 for c in record.final_classes)
        vector["smoke_instances"] += sum(c == 1 for c in record.final_classes)
        for annotation, final_class in zip(record.raw_lines, record.final_classes):
            vector[f"{('fire' if final_class == 0 else 'smoke')}_{bucket(annotation['width'] * annotation['height'])}"] += 1
    return vector


def build_splits(records: list[Record], output: Path, reports: Path) -> dict[str, list[Record]]:
    groups = make_groups(records)
    split_names = ("train", "val", "test")
    ratios = {"train": .75, "val": .15, "test": .10}
    totals = feature_vector(records)
    dimensions = ["total", "fire_images", "smoke_images", "negative_images", "fire_instances", "smoke_instances"] + [f"{kind}_{size}" for kind in ("fire", "smoke") for size in SIZE_BUCKETS]
    targets = {split: {key: totals[key] * ratios[split] for key in dimensions} for split in split_names}
    current = {split: Counter() for split in split_names}
    assignments: dict[str, str] = {}
    ordered = list(groups.items())
    random.Random(SEED).shuffle(ordered)
    ordered.sort(key=lambda item: (-sum(len(r.final_lines) for r in item[1]), -len(item[1]), item[0]))
    for group_id, group_records in ordered:
        vector = feature_vector(group_records)
        def score(split: str) -> float:
            value = 0.0
            for key in dimensions:
                target = max(targets[split][key], 1.0)
                value += ((current[split][key] + vector[key] - targets[split][key]) / target) ** 2
            # Encourage every source to be represented in every split when possible.
            value += sum(0.05 for r in group_records if not any(x.source == r.source for g, xsplit in assignments.items() for x in groups[g] if xsplit == split))
            return value
        chosen = min(split_names, key=lambda split: (score(split), current[split]["total"], split))
        assignments[group_id] = chosen
        current[chosen].update(vector)
    split_records = {split: [] for split in split_names}
    for group_id, group_records in sorted(groups.items()):
        split_records[assignments[group_id]].extend(sorted(group_records, key=lambda r: r.image_key))
    for split, selected in split_records.items():
        (output / "images" / split).mkdir(parents=True, exist_ok=True)
        (output / "labels" / split).mkdir(parents=True, exist_ok=True)
        for record in selected:
            destination_name = prepared_image_name(record)
            image_target = output / "images" / split / destination_name
            label_target = output / "labels" / split / (Path(destination_name).stem + ".txt")
            if image_target.exists() or label_target.exists(): raise ValueError(f"Output collision: {destination_name}")
            shutil.copy2(record.image, image_target)
            label_target.write_text("\n".join(record.final_lines) + ("\n" if record.final_lines else ""), encoding="utf-8")
            record.notes.append(f"final_name={destination_name}")
    manifest_fields = ["asset_id", "prepared_filename", "split", "source_dataset", "original_split", "group_id", "sha256", "perceptual_hash", "decision", "has_fire", "has_smoke", "seed"]
    rows = []
    for split, selected in split_records.items():
        for record in selected:
            rows.append({"asset_id": hashlib.sha256(record.image_key.encode()).hexdigest()[:16], "prepared_filename": prepared_image_name(record), "split": split, "source_dataset": record.source, "original_split": record.original_split, "group_id": record.group_id, "sha256": record.sha256, "perceptual_hash": record.perceptual_hash, "decision": record.decision, "has_fire": 0 in record.final_classes, "has_smoke": 1 in record.final_classes, "seed": SEED})
    write_csv(output / "metadata" / "split_manifest.csv", manifest_fields, rows)
    write_csv(output / "metadata" / "source_records.csv", ["source", "original_split", "image", "sha256", "perceptual_hash", "decision", "group_id"], [{"source": r.source, "original_split": r.original_split, "image": r.image.name, "sha256": r.sha256, "perceptual_hash": r.perceptual_hash, "decision": r.decision, "group_id": r.group_id} for r in records])
    summary = {"seed": SEED, "ratios": ratios, "counts": {split: len(items) for split, items in split_records.items()}, "features": {split: dict(feature_vector(items)) for split, items in split_records.items()}, "groups": len(groups)}
    (output / "metadata" / "split_summary.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
    return split_records


def write_yaml(output: Path) -> None:
    (output / "data.yaml").write_text("path: .\ntrain: images/train\nval: images/val\ntest: images/test\n\nnames:\n  0: fire\n  1: smoke\nnc: 2\n", encoding="utf-8")


def validate_final(output: Path, reports: Path) -> tuple[bool, list[dict]]:
    rows, all_images = [], []
    split_records: dict[str, list[tuple[Path, Path | None]]] = {}
    for split in ("train", "val", "test"):
        image_dir, label_dir = output / "images" / split, output / "labels" / split
        images = sorted(p for p in image_dir.glob("*") if p.is_file() and p.suffix.lower() in IMAGE_EXTENSIONS)
        labels = {p.stem: p for p in label_dir.glob("*.txt")}
        with ThreadPoolExecutor(max_workers=8) as executor:
            image_validity = dict(zip(images, executor.map(is_valid_image, images)))
        selected = []
        stats = Counter()
        for image in images:
            label = labels.get(image.stem)
            selected.append((image, label)); all_images.append((split, image))
            valid_image = image_validity[image]
            if not valid_image: stats["corrupt_images"] += 1
            if label is None: stats["images_without_labels"] += 1; continue
            lines = label.read_text(encoding="utf-8-sig", errors="replace").splitlines()
            for line_number, raw in enumerate(lines, 1):
                if not raw.strip(): continue
                parts = raw.split()
                if len(parts) != 5:
                    stats["invalid_labels"] += 1; continue
                try: class_id = int(parts[0]); values = [float(v) for v in parts[1:]]
                except ValueError: stats["invalid_labels"] += 1; continue
                x, y, width, height = values
                if class_id not in FINAL_NAMES or not all(math.isfinite(v) and 0 <= v <= 1 for v in values) or width <= 0 or height <= 0 or x-width/2 < -1e-9 or y-height/2 < -1e-9 or x+width/2 > 1+1e-9 or y+height/2 > 1+1e-9:
                    stats["invalid_labels"] += 1; continue
                kind = "fire" if class_id == 0 else "smoke"
                stats[f"{kind}_instances"] += 1; stats[f"{kind}_{bucket(width*height)}"] += 1
            if not any(raw.strip() for raw in lines): stats["negative_images"] += 1
            else:
                classes = {int(raw.split()[0]) for raw in lines if raw.strip() and len(raw.split()) == 5 and raw.split()[0].isdigit()}
                stats["images_with_fire"] += int(0 in classes); stats["images_with_smoke"] += int(1 in classes)
        image_stems = {image.stem for image in images}
        stats["labels_without_images"] = sum(stem not in image_stems for stem in labels)
        split_records[split] = selected
        rows.append({"split": split, "images": len(images), **{key: stats[key] for key in FINAL_FIELDS if key not in {"split", "images", "exact_inter_split_duplicates", "near_inter_split_duplicates"}}})
    exact = defaultdict(list); phash = defaultdict(list)
    manifest_rows = read_csv(output / "metadata" / "split_manifest.csv")
    if manifest_rows:
        for row in manifest_rows:
            split = row["split"]
            name = row["prepared_filename"]
            exact[row["sha256"]].append(split + ":" + name)
            phash[row["perceptual_hash"]].append((split, name))
    else:
        for split, image in all_images:
            try:
                exact[sha256(image)].append(split + ":" + image.name)
                phash[perceptual_hash(image)].append((split, image.name))
            except Exception: pass
    exact_cross = [items for items in exact.values() if len({item.split(":", 1)[0] for item in items}) > 1]
    near_cross_count = 0
    near_cross_examples = []
    for items in phash.values():
        by_split = defaultdict(list)
        for split, name in items:
            by_split[split].append(name)
        split_names = sorted(by_split)
        for left_index, left_split in enumerate(split_names):
            for right_split in split_names[left_index + 1:]:
                pair_count = len(by_split[left_split]) * len(by_split[right_split])
                near_cross_count += pair_count
                if len(near_cross_examples) < 50 and pair_count:
                    near_cross_examples.append((left_split + ":" + by_split[left_split][0], right_split + ":" + by_split[right_split][0]))
    for row in rows:
        row["exact_inter_split_duplicates"] = len(exact_cross); row["near_inter_split_duplicates"] = near_cross_count
    write_csv(reports / "v2_final_statistics.csv", FINAL_FIELDS, rows)
    total = Counter()
    for row in rows:
        total.update({key: int(row[key]) for key in FINAL_FIELDS if key not in {"split"} and str(row[key]).isdigit()})
    pass_gate = not exact_cross and near_cross_count == 0 and all(all(int(row[key]) == 0 for key in ("corrupt_images", "invalid_labels", "images_without_labels", "labels_without_images")) for row in rows)
    lines = ["# V2 final validation report", "", f"Validation result: **{'PASS' if pass_gate else 'FAIL'}**", "", "## Split statistics", "", "| split | images | negatives | fire instances | smoke instances | images fire | images smoke |", "|---|---:|---:|---:|---:|---:|---:|"]
    for row in rows: lines.append(f"| {row['split']} | {row['images']} | {row['negative_images']} | {row['fire_instances']} | {row['smoke_instances']} | {row['images_with_fire']} | {row['images_with_smoke']} |")
    lines += ["", "## Gates", "", f"- Corrupt retained images: {sum(int(r['corrupt_images']) for r in rows)}", f"- Invalid retained annotations: {sum(int(r['invalid_labels']) for r in rows)}", f"- Images without labels: {sum(int(r['images_without_labels']) for r in rows)}", f"- Labels without images: {sum(int(r['labels_without_images']) for r in rows)}", f"- Exact cross-split duplicates: {len(exact_cross)}", f"- Perceptual cross-split pairs (exact average-hash equality): {near_cross_count}", "- Final class IDs allowed: 0, 1", "", "The final dataset is a copy assembled from raw files; raw sources and V1 were not written by this pipeline.", ""]
    if near_cross_examples: lines += ["## Near-duplicate examples", ""] + [f"- `{a}` <-> `{b}`" for a, b in near_cross_examples] + [""]
    (reports / "V2_FINAL_VALIDATION_REPORT.md").write_text("\n".join(lines), encoding="utf-8")
    return pass_gate, rows


def write_handoff_reports(output: Path, reports: Path, dedup_rows: list[dict], issues: list[dict], split_records: dict[str, list[Record]], review: list[Record], validation_pass: bool, final_rows: list[dict]) -> None:
    # Reports are written directly to output/reports.  This hook remains so a
    # future caller can add a second publication target without changing the
    # pipeline stages.
    output.joinpath("reports").mkdir(parents=True, exist_ok=True)


def choose_output(requested: Path) -> Path:
    if not requested.exists() or not any(requested.iterdir()): return requested
    if (requested / "reports" / "v2_source_inventory.csv").exists() and not (requested / "data.yaml").exists():
        return requested
    children = list(requested.iterdir())
    if len(children) == 1 and children[0].is_dir() and children[0].name == "reports" and not any(children[0].iterdir()):
        return requested
    if len(children) == 1 and children[0].is_dir() and children[0].name == "reports" and (children[0] / "v2_source_inventory.csv").exists():
        return requested
    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    candidate = requested.with_name(requested.name + "__" + stamp)
    suffix = 1
    while candidate.exists():
        candidate = requested.with_name(requested.name + "__" + stamp + f"_{suffix}"); suffix += 1
    return candidate


def run(args: argparse.Namespace) -> int:
    raw_root = args.raw.resolve(); output = choose_output(args.output.resolve()); output.mkdir(parents=True, exist_ok=True); reports = output / "reports"
    reports.mkdir(parents=True, exist_ok=True)
    inventory_cache = reports / "v2_source_inventory.csv"
    print(f"[dataset_v2] output={output}", file=sys.stderr, flush=True)
    print("[dataset_v2] loading inventory", file=sys.stderr, flush=True)
    records = records_from_inventory(raw_root, reports) if inventory_cache.exists() else inventory_sources(raw_root, reports)
    print(f"[dataset_v2] records={len(records)}", file=sys.stderr, flush=True)
    print("[dataset_v2] deduplicating", file=sys.stderr, flush=True)
    records, dedup_rows = deduplicate(records, reports)
    print(f"[dataset_v2] records_after_dedup={len(records)}", file=sys.stderr, flush=True)
    print("[dataset_v2] applying class mapping", file=sys.stderr, flush=True)
    accepted, review, _ = apply_mapping(records, load_mapping(args.mapping), reports, output)
    print(f"[dataset_v2] accepted={len(accepted)} manual_review={len(review)}", file=sys.stderr, flush=True)
    print("[dataset_v2] analyzing smoke sizes", file=sys.stderr, flush=True)
    smoke_analysis(accepted, reports)
    print("[dataset_v2] auditing annotations", file=sys.stderr, flush=True)
    issues = annotation_audit(records, review, reports, output)
    output.mkdir(parents=True, exist_ok=True)
    print("[dataset_v2] copying manual review", file=sys.stderr, flush=True)
    copy_manual_review(review, output)
    print("[dataset_v2] writing hard-negative report", file=sys.stderr, flush=True)
    hard_negative_report(accepted, reports)
    print("[dataset_v2] building final splits", file=sys.stderr, flush=True)
    split_records = build_splits(accepted, output, reports)
    print("[dataset_v2] writing data.yaml", file=sys.stderr, flush=True)
    write_yaml(output)
    print("[dataset_v2] validating final dataset", file=sys.stderr, flush=True)
    validation_pass, final_rows = validate_final(output, reports)
    write_handoff_reports(output, reports, dedup_rows, issues, split_records, review, validation_pass, final_rows)
    print(json.dumps({"output": str(output), "sources": sorted({r.source for r in records}), "accepted_images": len(accepted), "manual_review": len(review), "split_counts": {k: len(v) for k, v in split_records.items()}, "validation": "PASS" if validation_pass else "FAIL"}, indent=2))
    return 0 if validation_pass else 2


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("command", choices=("run",), help="run the complete non-training pipeline")
    parser.add_argument("--raw", type=Path, default=RAW_ROOT)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT)
    parser.add_argument("--mapping", type=Path, default=MAPPING_PATH)
    args = parser.parse_args()
    return run(args)


if __name__ == "__main__":
    raise SystemExit(main())
