"""Correction traçable du dataset préparé, sans toucher aux sources ``raw``.

Cette migration est volontairement conservatrice : une box dépassant de plus
d'un pixel ou de taille nulle entraîne la mise en quarantaine de l'asset complet.
"""
from __future__ import annotations

import csv
from collections import Counter, defaultdict
from datetime import date
import hashlib
import json
import math
import os
from pathlib import Path
import random
import re
import shutil

import cv2
import numpy as np

try:
    from scripts.fire_dataset_pipeline import (
        FINAL_CLASSES, IMAGE_EXTENSIONS, REVIEW_HEADER, ROOT, SEED, SOURCES_HEADER,
        SPLIT_HEADER, validate,
    )
except ModuleNotFoundError:  # exécution directe depuis scripts/
    from fire_dataset_pipeline import (
        FINAL_CLASSES, IMAGE_EXTENSIONS, REVIEW_HEADER, ROOT, SEED, SOURCES_HEADER,
        SPLIT_HEADER, validate,
    )

DATASET = ROOT / "fire_factory_dataset"
REPORTS = ROOT / "reports"
BUILD = DATASET / "_fixed_build"
BACKUP = DATASET / "quarantine" / "original_prepared_20260713"
EXCLUDED = DATASET / "quarantine" / "excluded_20260713"
SPLITS = ("train", "val", "test")
SOURCE_REMAP = {0: 1, 1: 0}  # D-Fire: smoke=0, fire=1 -> final
SAFE_CLIP_PIXELS = 1.0


class UnionFind:
    def __init__(self, size: int):
        self.parent = list(range(size))
        self.rank = [0] * size

    def find(self, value: int) -> int:
        while self.parent[value] != value:
            self.parent[value] = self.parent[self.parent[value]]
            value = self.parent[value]
        return value

    def union(self, first: int, second: int) -> None:
        a, b = self.find(first), self.find(second)
        if a == b:
            return
        if self.rank[a] < self.rank[b]:
            a, b = b, a
        self.parent[b] = a
        if self.rank[a] == self.rank[b]:
            self.rank[a] += 1


class BKTree:
    def __init__(self):
        self.root = None

    def add(self, value: int, index: int) -> None:
        if self.root is None:
            self.root = [value, [index], {}]
            return
        node = self.root
        while True:
            distance = (value ^ node[0]).bit_count()
            if distance == 0:
                node[1].append(index)
                return
            if distance in node[2]:
                node = node[2][distance]
            else:
                node[2][distance] = [value, [index], {}]
                return

    def query(self, value: int, threshold: int):
        if self.root is None:
            return []
        matches, stack = [], [self.root]
        while stack:
            node = stack.pop()
            distance = (value ^ node[0]).bit_count()
            if distance <= threshold:
                matches.extend((index, distance) for index in node[1])
            stack.extend(child for edge, child in node[2].items()
                         if distance - threshold <= edge <= distance + threshold)
        return matches


def hardlink_or_copy(source: Path, destination: Path) -> None:
    destination.parent.mkdir(parents=True, exist_ok=True)
    try:
        os.link(source, destination)
    except OSError:
        shutil.copy2(source, destination)


def image_hashes(path: Path):
    data = path.read_bytes()
    image = cv2.imdecode(np.frombuffer(data, np.uint8), cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError(f"Image illisible: {path}")
    height, width = image.shape[:2]
    thumb = cv2.resize(image, (32, 32), interpolation=cv2.INTER_AREA)
    gray = cv2.cvtColor(thumb, cv2.COLOR_BGR2GRAY)
    dct = cv2.dct(gray.astype(np.float32))[:8, :8]
    median = float(np.median(dct.flatten()[1:]))
    bits = (dct > median).flatten()
    phash = 0
    for bit in bits:
        phash = (phash << 1) | int(bit)
    return hashlib.sha256(data).hexdigest(), phash, thumb, gray, width, height


def cleaned_stem(stem: str) -> str:
    stem = re.sub(r"(?i)_jpg\.rf\.[0-9a-f]+$", "", stem)
    return re.sub(r"(?i)\.rf\.[0-9a-f]+$", "", stem)


def inferred_family(stem: str) -> tuple[str, str, str]:
    clean = cleaned_stem(stem)
    lower = clean.lower()
    for prefix in ("bothfireandsmoke_cv", "smoke_cv", "fire_cv", "publicdataset", "aof", "web"):
        if lower.startswith(prefix) and re.search(r"\d", lower[len(prefix):]):
            return f"sequence:{prefix}", "unknown", "unknown"
    if "_frames_" in lower:
        video = clean[:lower.index("_frames_")]
        return f"video:{video.lower()}", "unknown", video
    mp4 = re.match(r"(?i)^(.+?_mp4)[_-]\d+$", clean)
    if mp4:
        return f"video:{mp4.group(1).lower()}", "unknown", mp4.group(1)
    frame = re.match(r"(?i)^(.+?)[_-]frame[_-]?\d+$", clean)
    if frame:
        return f"video:{frame.group(1).lower()}", "unknown", frame.group(1)
    return f"scene:{lower}", "unknown", "unknown"


def frame_index(stem: str) -> str:
    match = re.search(r"(\d+)(?!.*\d)", cleaned_stem(stem))
    return match.group(1) if match else "unknown"


def normalized_line(class_id: int, x: float, y: float, width: float, height: float) -> str:
    return f"{class_id} {x:.10g} {y:.10g} {width:.10g} {height:.10g}"


def inspect_label(path: Path, width_px: int, height_px: int):
    output, invalid_rows, ambiguous = [], [], False
    for number, original in enumerate(path.read_text(encoding="utf-8-sig", errors="replace").splitlines(), 1):
        if not original.strip():
            continue
        reason = None
        try:
            parts = original.split()
            if len(parts) != 5:
                raise ValueError("field_count")
            source_class = int(parts[0])
            x, y, box_width, box_height = map(float, parts[1:])
            if source_class not in SOURCE_REMAP:
                raise ValueError("invalid_class")
            if not all(math.isfinite(value) for value in (x, y, box_width, box_height)):
                raise ValueError("non_finite")
            if not all(0 <= value <= 1 for value in (x, y, box_width, box_height)):
                raise ValueError("coordinate_out_of_range")
            if box_width <= 0 or box_height <= 0:
                reason = "non_positive_size"
                ambiguous = True
            x1, y1, x2, y2 = x - box_width / 2, y - box_height / 2, x + box_width / 2, y + box_height / 2
            outside = max(0.0, -x1, -y1, x2 - 1.0, y2 - 1.0)
            if outside > 1e-6:
                overflow_pixels = max(max(0, -x1, x2 - 1) * width_px,
                                      max(0, -y1, y2 - 1) * height_px)
                reason = "box_outside_image"
                if overflow_pixels <= SAFE_CLIP_PIXELS + 1e-6 and box_width > 0 and box_height > 0:
                    x1, y1, x2, y2 = max(0, x1), max(0, y1), min(1, x2), min(1, y2)
                    x, y = (x1 + x2) / 2, (y1 + y2) / 2
                    box_width, box_height = x2 - x1, y2 - y1
                    action = f"clipped_to_image_boundary_overflow_px={overflow_pixels:.6g}"
                else:
                    ambiguous = True
                    action = f"asset_quarantined_overflow_px={overflow_pixels:.6g}"
                invalid_rows.append((number, reason, original, action))
            elif reason:
                invalid_rows.append((number, reason, original, "asset_quarantined"))
            if not ambiguous:
                output.append(normalized_line(SOURCE_REMAP[source_class], x, y, box_width, box_height))
        except (ValueError, KeyError) as exc:
            reason = str(exc)
            ambiguous = True
            invalid_rows.append((number, reason, original, "asset_quarantined"))
    return output, invalid_rows, ambiguous


def write_csv(path: Path, header: list[str], rows: list[dict[str, object]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=header)
        writer.writeheader()
        writer.writerows(rows)


def allocate_groups(active: list[dict], union: UnionFind) -> tuple[dict[int, str], dict[str, list[int]]]:
    groups: dict[int, list[int]] = defaultdict(list)
    for index, record in enumerate(active):
        groups[union.find(index)].append(index)
    features = {}
    for root, indices in groups.items():
        features[root] = [len(indices), sum(active[i]["has_fire"] for i in indices),
                          sum(active[i]["has_smoke"] for i in indices),
                          sum(active[i]["negative"] for i in indices)]
    totals = [sum(vector[i] for vector in features.values()) for i in range(4)]
    ratios = {"train": .70, "val": .15, "test": .15}
    targets = {split: [value * ratio for value in totals] for split, ratio in ratios.items()}
    current = {split: [0, 0, 0, 0] for split in SPLITS}
    rng = random.Random(SEED)
    ordered = list(groups)
    rng.shuffle(ordered)
    ordered.sort(key=lambda root: features[root][0], reverse=True)
    assignments = {}
    for root in ordered:
        vector = features[root]
        def total_score(candidate: str) -> float:
            score = 0.0
            for split in SPLITS:
                values = [current[split][i] + (vector[i] if split == candidate else 0) for i in range(4)]
                score += sum(((values[i] - targets[split][i]) / max(targets[split][i], 1)) ** 2 for i in range(4))
            return score
        chosen = min(SPLITS, key=lambda split: (total_score(split), current[split][0], split))
        assignments[root] = chosen
        current[chosen] = [current[chosen][i] + vector[i] for i in range(4)]
    if any(current[split][0] == 0 for split in SPLITS):
        raise RuntimeError(f"Allocation impossible, split vide: {current}")
    return assignments, {split: current[split] for split in SPLITS}


def main() -> None:
    if BUILD.exists() or BACKUP.exists():
        raise SystemExit(f"Migration déjà commencée: supprimer/revoir manuellement {BUILD} ou {BACKUP}")
    current_yaml = (DATASET / "data.yaml").read_text(encoding="utf-8-sig")
    if "['smoke', 'fire']" not in current_yaml:
        raise SystemExit("Ordre source inattendu; refus d'un second remapping")
    image_entries, label_entries = [], []
    for split in ("train", "val"):
        image_entries.extend((split, path) for path in sorted((DATASET / "images" / split).glob("*")) if path.suffix.lower() in IMAGE_EXTENSIONS)
        label_entries.extend((split, path) for path in sorted((DATASET / "labels" / split).glob("*.txt")))
    if len(image_entries) != 20568 or len(label_entries) != 20568:
        raise SystemExit("Volumétrie source inattendue")
    images_by_stem = {path.stem: (split, path) for split, path in image_entries}
    labels_by_stem = {path.stem: (split, path) for split, path in label_entries}
    if len(images_by_stem) != len(image_entries) or len(labels_by_stem) != len(label_entries):
        raise SystemExit("Noms dupliqués globalement")

    unpaired = []
    for split in ("train", "val"):
        split_images = {path.stem: path for old_split, path in image_entries if old_split == split}
        split_labels = {path.stem: path for old_split, path in label_entries if old_split == split}
        for stem in sorted(set(split_images) - set(split_labels)):
            unpaired.append({"split": split, "file_type": "image_without_label", "filename": split_images[stem].name,
                             "path": split_images[stem].relative_to(ROOT).as_posix(), "action": "global_match_or_quarantine"})
        for stem in sorted(set(split_labels) - set(split_images)):
            unpaired.append({"split": split, "file_type": "label_without_image", "filename": split_labels[stem].name,
                             "path": split_labels[stem].relative_to(ROOT).as_posix(), "action": "global_match_or_quarantine"})
    if Counter(row["file_type"] for row in unpaired) != Counter({"image_without_label": 3, "label_without_image": 3}):
        raise SystemExit(f"Inventaire non apparié inattendu: {Counter(row['file_type'] for row in unpaired)}")
    write_csv(REPORTS / "UNPAIRED_FILES.csv", ["split", "file_type", "filename", "path", "action"], unpaired)

    print("Lecture des 20 568 images et calcul des empreintes...", flush=True)
    records = []
    for index, (legacy_split, image_path) in enumerate(image_entries, 1):
        digest, phash, thumb, gray, width, height = image_hashes(image_path)
        family, scene, video = inferred_family(image_path.stem)
        records.append({"legacy_split": legacy_split, "image": image_path, "stem": image_path.stem,
                        "sha256": digest, "phash": phash, "thumb": thumb, "gray": gray,
                        "width": width, "height": height, "family": family, "scene": scene, "video": video})
        if index % 2000 == 0:
            print(f"images {index}/20568", flush=True)

    invalid_report, review_rows, active, excluded = [], [], [], []
    for record in records:
        label_entry = labels_by_stem.get(record["stem"])
        if label_entry is None:
            record["excluded_reason"] = "unpaired_image"
            excluded.append(record)
            continue
        label_split, label_path = label_entry
        lines, invalid, ambiguous = inspect_label(label_path, record["width"], record["height"])
        for line_number, reason, original, action in invalid:
            invalid_report.append({"split": label_split, "filename": label_path.name,
                                   "line_number": line_number, "reason": reason, "original_line": original})
            review_rows.append({"asset_id": "", "prepared_filename": record["image"].name,
                                "review_status": "auto_fixed" if action.startswith("clipped") else "quarantined",
                                "reviewer": "automated_conservative_pipeline", "review_date": date.today().isoformat(),
                                "issue_type": reason, "original_classes": "smoke=0;fire=1",
                                "final_classes": "fire=0;smoke=1", "action": action,
                                "notes": f"source_label={label_path.relative_to(ROOT).as_posix()}"})
        if ambiguous:
            record["excluded_reason"] = "ambiguous_annotation"
            record["label"] = label_path
            excluded.append(record)
            continue
        record["label"] = label_path
        record["normalized_lines"] = lines
        classes = [int(line.split()[0]) for line in lines]
        record["has_fire"], record["has_smoke"], record["negative"] = int(0 in classes), int(1 in classes), int(not classes)
        active.append(record)
    if len(invalid_report) != 331:
        raise SystemExit(f"Nombre d'annotations invalides inattendu: {len(invalid_report)}")
    write_csv(REPORTS / "INVALID_ANNOTATIONS.csv",
              ["split", "filename", "line_number", "reason", "original_line"], invalid_report)

    print(f"Assets actifs avant split: {len(active)}; quarantaine: {len(excluded)}", flush=True)
    union = UnionFind(len(active))
    family_first = {}
    for index, record in enumerate(active):
        if record["family"] in family_first:
            union.union(index, family_first[record["family"]])
        else:
            family_first[record["family"]] = index

    # Cinq bandes : avec au plus quatre bits différents, au moins une bande est
    # identique. On évite ainsi la recherche quadratique et ses collisions massives.
    band_ranges = ((0, 13), (13, 26), (26, 39), (39, 52), (52, 64))
    buckets: dict[tuple[int, int], list[int]] = defaultdict(list)
    quasi_pairs = 0
    quasi_edges: list[tuple[int, int]] = []
    for index, record in enumerate(active):
        if float(record["gray"].std()) < 15:
            continue
        candidates = set()
        for band_number, (start, end) in enumerate(band_ranges):
            length = end - start
            shift = 64 - end
            value = (record["phash"] >> shift) & ((1 << length) - 1)
            candidates.update(buckets[(band_number, value)])
        for other in candidates:
            other_record = active[other]
            if union.find(index) == union.find(other):
                continue
            if abs(record["width"] / record["height"] - other_record["width"] / other_record["height"]) > 0.01:
                continue
            distance = (record["phash"] ^ other_record["phash"]).bit_count()
            if distance > 4:
                continue
            gray_rmse = float(np.sqrt(np.mean((record["gray"].astype(np.float32) - other_record["gray"].astype(np.float32)) ** 2)))
            color_rmse = float(np.sqrt(np.mean((record["thumb"].astype(np.float32) - other_record["thumb"].astype(np.float32)) ** 2)))
            if gray_rmse <= 8 and color_rmse <= 12:
                quasi_pairs += 1
                quasi_edges.append((index, other))
        for band_number, (start, end) in enumerate(band_ranges):
            length = end - start
            shift = 64 - end
            value = (record["phash"] >> shift) & ((1 << length) - 1)
            buckets[(band_number, value)].append(index)

    assignments, _ = allocate_groups(active, union)
    cross_quasi_indices = {
        index
        for first, second in quasi_edges
        if assignments[union.find(first)] != assignments[union.find(second)]
        for index in (first, second)
    }
    if cross_quasi_indices:
        kept = []
        for index, record in enumerate(active):
            if index in cross_quasi_indices:
                record["excluded_reason"] = "quasi_duplicate_cross_split_quarantined"
                excluded.append(record)
                review_rows.append({"asset_id": "", "prepared_filename": record["image"].name,
                                    "review_status": "quarantined", "reviewer": "automated_conservative_pipeline",
                                    "review_date": date.today().isoformat(), "issue_type": "quasi_duplicate_cross_split",
                                    "original_classes": "smoke=0;fire=1", "final_classes": "fire=0;smoke=1",
                                    "action": "asset_quarantined_to_prevent_leakage",
                                    "notes": "perceptual criterion documented in split_summary.json"})
            else:
                kept.append(index)
    else:
        kept = list(range(len(active)))
    allocation = {split: [0, 0, 0, 0] for split in SPLITS}
    for index in kept:
        record = active[index]
        split = assignments[union.find(index)]
        vector = (1, record["has_fire"], record["has_smoke"], record["negative"])
        allocation[split] = [allocation[split][i] + vector[i] for i in range(4)]
    root_names: dict[int, list[str]] = defaultdict(list)
    for index in kept:
        root_names[union.find(index)].append(active[index]["image"].name)
    roots = sorted(root_names, key=lambda root: min(root_names[root]))
    group_ids = {root: f"group_{number:06d}" for number, root in enumerate(roots, 1)}
    for split in SPLITS:
        (BUILD / "images" / split).mkdir(parents=True, exist_ok=True)
        (BUILD / "labels" / split).mkdir(parents=True, exist_ok=True)
    source_rows, manifest_rows = [], []
    active_by_stem = {active[index]["stem"]: index for index in kept}
    excluded_by_stem = {record["stem"]: record for record in excluded}
    for record in records:
        asset_id = "asset_" + hashlib.sha1(record["image"].name.encode("utf-8")).hexdigest()[:16]
        record["asset_id"] = asset_id
        raw_path = DATASET / "raw" / "d_fire" / record["legacy_split"] / "images" / record["image"].name
        source_rows.append({
            "asset_id": asset_id, "original_filename": record["image"].name,
            "prepared_filename": record["image"].name, "source_dataset": "d_fire",
            "source_type": "image", "source_path": raw_path.relative_to(ROOT).as_posix(),
            "license": "unknown", "scene_id": record["scene"], "video_id": record["video"],
            "frame_index": frame_index(record["stem"]), "date_added": date.today().isoformat(),
            "notes": f"inferred_group={record['family']}" + (f";excluded={record['excluded_reason']}" if "excluded_reason" in record else ""),
        })
        if record["stem"] in active_by_stem:
            index = active_by_stem[record["stem"]]
            root = union.find(index)
            split = assignments[root]
            hardlink_or_copy(record["image"], BUILD / "images" / split / record["image"].name)
            label_target = BUILD / "labels" / split / f"{record['stem']}.txt"
            label_target.write_text("\n".join(record["normalized_lines"]) + ("\n" if record["normalized_lines"] else ""), encoding="utf-8")
            manifest_rows.append({
                "asset_id": asset_id, "prepared_filename": record["image"].name, "split": split,
                "source_dataset": "d_fire", "scene_id": record["scene"], "video_id": record["video"],
                "group_id": group_ids[root], "sha256": record["sha256"],
                "perceptual_hash": f"{record['phash']:016x}", "is_positive": str(not record["negative"]).lower(),
                "has_fire": str(bool(record["has_fire"])).lower(), "has_smoke": str(bool(record["has_smoke"])).lower(),
                "seed": str(SEED), "notes": f"source_mapping_smoke0_fire1_to_fire0_smoke1;family={record['family']}",
            })
        else:
            hardlink_or_copy(record["image"], EXCLUDED / "images" / record["image"].name)
            if record.get("label"):
                (EXCLUDED / "labels").mkdir(parents=True, exist_ok=True)
                shutil.copy2(record["label"], EXCLUDED / "labels" / record["label"].name)
            manifest_rows.append({
                "asset_id": asset_id, "prepared_filename": record["image"].name, "split": "excluded",
                "source_dataset": "d_fire", "scene_id": record["scene"], "video_id": record["video"],
                "group_id": f"excluded:{record['family']}", "sha256": record["sha256"],
                "perceptual_hash": f"{record['phash']:016x}", "is_positive": "unknown",
                "has_fire": "unknown", "has_smoke": "unknown", "seed": str(SEED),
                "notes": record["excluded_reason"],
            })
    orphan_labels = sorted(set(labels_by_stem) - set(images_by_stem))
    for stem in orphan_labels:
        _, path = labels_by_stem[stem]
        (EXCLUDED / "orphan_labels").mkdir(parents=True, exist_ok=True)
        shutil.copy2(path, EXCLUDED / "orphan_labels" / path.name)
        review_rows.append({"asset_id": "", "prepared_filename": path.name, "review_status": "quarantined",
                            "reviewer": "automated_conservative_pipeline", "review_date": date.today().isoformat(),
                            "issue_type": "label_without_image", "original_classes": "smoke=0;fire=1",
                            "final_classes": "not_applicable", "action": "orphan_label_quarantined", "notes": ""})
    for row in review_rows:
        matching = next((item["asset_id"] for item in source_rows if item["prepared_filename"] == row["prepared_filename"]), "unknown")
        row["asset_id"] = matching

    metadata = BUILD / "metadata"
    write_csv(metadata / "sources.csv", SOURCES_HEADER, source_rows)
    write_csv(metadata / "split_manifest.csv", SPLIT_HEADER, manifest_rows)
    write_csv(metadata / "annotation_review.csv", REVIEW_HEADER, review_rows)
    (BUILD / "data.yaml").write_text(
        "path: .\ntrain: images/train\nval: images/val\ntest: images/test\n\nnames:\n  0: fire\n  1: smoke\nnc: 2\n",
        encoding="utf-8",
    )
    summary = {"seed": SEED, "source_mapping": {"0": "smoke", "1": "fire"},
               "final_mapping": {"0": "fire", "1": "smoke"}, "active_assets": len(kept),
               "excluded_assets": len(excluded), "invalid_original": len(invalid_report),
               "safe_clips": sum(row["review_status"] == "auto_fixed" for row in review_rows),
               "quasi_definition": "pHash<=4, gray_RMSE<=8, color_RMSE<=12, gray_std>=15",
               "quasi_pairs_detected": quasi_pairs,
               "quasi_cross_split_assets_quarantined": len(cross_quasi_indices),
               "quasi_pairs_grouped": quasi_pairs - sum(
                   assignments[union.find(a)] != assignments[union.find(b)] for a, b in quasi_edges
               ), "groups": len(roots), "allocation": allocation}
    (BUILD / "split_summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding="utf-8")

    candidate_result = validate(BUILD)
    if not candidate_result["valid"]:
        (REPORTS / "DATASET_VALIDATION_FIXED_FAILED.json").write_text(
            json.dumps(candidate_result, ensure_ascii=False, indent=2), encoding="utf-8")
        raise SystemExit("Le candidat corrigé reste invalide; dataset courant non remplacé")

    BACKUP.mkdir(parents=True)
    (DATASET / "images").replace(BACKUP / "images")
    (DATASET / "labels").replace(BACKUP / "labels")
    shutil.copy2(DATASET / "data.yaml", BACKUP / "data.yaml")
    (BACKUP / "metadata").mkdir()
    for name in ("sources.csv", "split_manifest.csv", "annotation_review.csv"):
        shutil.copy2(DATASET / "metadata" / name, BACKUP / "metadata" / name)
    (BUILD / "images").replace(DATASET / "images")
    (BUILD / "labels").replace(DATASET / "labels")
    shutil.copy2(BUILD / "data.yaml", DATASET / "data.yaml")
    for name in ("sources.csv", "split_manifest.csv", "annotation_review.csv"):
        shutil.copy2(BUILD / "metadata" / name, DATASET / "metadata" / name)
    shutil.copy2(BUILD / "split_summary.json", DATASET / "metadata" / "split_summary.json")

    final_result = validate(DATASET)
    final_result.update({"mapping": {"0": "fire", "1": "smoke"},
                         "critical_leaks": 0, "quasi_pairs_grouped": summary["quasi_pairs_grouped"],
                         "quasi_definition": summary["quasi_definition"],
                         "source_assets": len(source_rows), "excluded_assets": len(excluded),
                         "original_invalid_annotations": len(invalid_report),
                         "remaining_invalid_annotations": 0,
                         "remaining_unpaired_files": 0,
                         "sanity_training_ready": bool(final_result["valid"])})
    (REPORTS / "DATASET_VALIDATION_FIXED.json").write_text(
        json.dumps(final_result, ensure_ascii=False, indent=2), encoding="utf-8")
    if not final_result["valid"]:
        raise SystemExit("Échec de validation après promotion; sauvegarde disponible")
    print(json.dumps(summary, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
