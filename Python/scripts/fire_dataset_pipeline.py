"""Pipeline reproductible du dataset fire/smoke, sans mutation des sources raw.

Les commandes de conversion écrivent dans un dossier de staging. La commande
``validate`` est la porte obligatoire de ``train`` et échoue au moindre défaut.
"""
from __future__ import annotations

import argparse
import csv
from collections import Counter, defaultdict
from datetime import date
import hashlib
import json
from pathlib import Path
import random
import shutil
import sys
import xml.etree.ElementTree as ET

IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".bmp", ".webp", ".tif", ".tiff"}
FINAL_CLASSES = {0: "fire", 1: "smoke"}
D_FIRE_REMAP = {0: 1, 1: 0}  # source: smoke=0, fire=1
SEED = 20260713
ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DATASET = ROOT / "fire_factory_dataset"

SOURCES_HEADER = [
    "asset_id", "original_filename", "prepared_filename", "source_dataset",
    "source_type", "source_path", "license", "scene_id", "video_id",
    "frame_index", "date_added", "notes",
]
SPLIT_HEADER = [
    "asset_id", "prepared_filename", "split", "source_dataset", "scene_id",
    "video_id", "group_id", "sha256", "perceptual_hash", "is_positive",
    "has_fire", "has_smoke", "seed", "notes",
]
REVIEW_HEADER = [
    "asset_id", "prepared_filename", "review_status", "reviewer", "review_date",
    "issue_type", "original_classes", "final_classes", "action", "notes",
]


def files(path: Path, suffixes: set[str]) -> list[Path]:
    return sorted(p for p in path.rglob("*") if p.is_file() and p.suffix.lower() in suffixes)


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def init_metadata(dataset: Path) -> None:
    metadata = dataset / "metadata"
    metadata.mkdir(parents=True, exist_ok=True)
    for name, header in (
        ("sources.csv", SOURCES_HEADER),
        ("split_manifest.csv", SPLIT_HEADER),
        ("annotation_review.csv", REVIEW_HEADER),
    ):
        target = metadata / name
        if target.exists() and target.stat().st_size:
            with target.open(encoding="utf-8-sig", newline="") as stream:
                actual = next(csv.reader(stream), [])
            if actual != header:
                raise ValueError(f"En-tête inattendu dans {target}: {actual}")
            continue
        with target.open("w", encoding="utf-8", newline="") as stream:
            csv.writer(stream).writerow(header)


def parse_yolo(path: Path, remap: dict[int, int] | None = None) -> tuple[list[str], list[str]]:
    converted, errors = [], []
    for line_number, raw_line in enumerate(path.read_text(encoding="utf-8-sig", errors="replace").splitlines(), 1):
        if not raw_line.strip():
            continue
        parts = raw_line.split()
        try:
            if len(parts) != 5:
                raise ValueError(f"5 champs attendus, {len(parts)} reçus")
            class_id = int(parts[0])
            x, y, width, height = map(float, parts[1:])
            if class_id not in FINAL_CLASSES:
                raise ValueError(f"classe {class_id} interdite")
            if not all(0.0 <= value <= 1.0 for value in (x, y, width, height)):
                raise ValueError("coordonnée hors [0,1]")
            if width <= 0.0 or height <= 0.0:
                raise ValueError("largeur/hauteur nulle")
            if x - width / 2 < -1e-6 or y - height / 2 < -1e-6 or x + width / 2 > 1 + 1e-6 or y + height / 2 > 1 + 1e-6:
                raise ValueError("box hors image")
            class_id = remap[class_id] if remap else class_id
            converted.append(f"{class_id} {x:.10g} {y:.10g} {width:.10g} {height:.10g}")
        except (ValueError, KeyError) as exc:
            errors.append(f"{path}:{line_number}: {exc}")
    return converted, errors


def convert_d_fire(dataset: Path, output: Path) -> None:
    """Standardise D-Fire dans un pool; n'invente, ne clippe et ne splitte rien."""
    raw = dataset / "raw" / "d_fire"
    image_out, label_out = output / "images", output / "labels"
    image_out.mkdir(parents=True, exist_ok=True)
    label_out.mkdir(parents=True, exist_ok=True)
    rejected: list[dict[str, str]] = []
    accepted = 0
    for legacy_split in ("train", "val", "test"):
        image_dir, label_dir = raw / legacy_split / "images", raw / legacy_split / "labels"
        images = {p.stem: p for p in files(image_dir, IMAGE_EXTENSIONS)} if image_dir.exists() else {}
        labels = {p.stem: p for p in files(label_dir, {".txt"})} if label_dir.exists() else {}
        for stem in sorted(set(images) | set(labels)):
            if stem not in images or stem not in labels:
                rejected.append({"asset": stem, "reason": "image_or_label_missing", "legacy_split": legacy_split})
                continue
            lines, errors = parse_yolo(labels[stem], D_FIRE_REMAP)
            if errors:
                rejected.append({"asset": stem, "reason": " | ".join(errors), "legacy_split": legacy_split})
                continue
            destination = image_out / f"dfire__{images[stem].name}"
            shutil.copy2(images[stem], destination)
            (label_out / f"dfire__{stem}.txt").write_text("\n".join(lines) + ("\n" if lines else ""), encoding="utf-8")
            accepted += 1
    (output / "conversion_report.json").write_text(
        json.dumps({"source": "d_fire", "accepted": accepted, "rejected": rejected,
                    "class_remap": {"0": 1, "1": 0}, "seed": SEED}, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    print(f"D-Fire standardisé: {accepted}; rejetés: {len(rejected)}; sortie: {output}")


def convert_voc(dataset: Path, output: Path) -> None:
    annotations = dataset / "raw" / "external_datasets" / "Annotations"
    image_source = dataset / "raw" / "external_datasets" / "Datacluster"
    source_images = {p.stem: p for p in files(image_source, IMAGE_EXTENSIONS)}
    image_out, label_out = output / "images", output / "labels"
    image_out.mkdir(parents=True, exist_ok=True)
    label_out.mkdir(parents=True, exist_ok=True)
    accepted, rejected = 0, []
    for xml_path in files(annotations, {".xml"}):
        try:
            root = ET.parse(xml_path).getroot()
            filename = root.findtext("filename") or ""
            image = source_images[Path(filename).stem]
            size = root.find("size")
            width, height = int(size.findtext("width")), int(size.findtext("height"))
            lines = []
            for obj in root.findall("object"):
                name = (obj.findtext("name") or "").strip().lower()
                if name != "fire":
                    raise ValueError(f"classe VOC non autorisée: {name}")
                box = obj.find("bndbox")
                xmin, ymin, xmax, ymax = [float(box.findtext(key)) for key in ("xmin", "ymin", "xmax", "ymax")]
                if not (0 <= xmin < xmax <= width and 0 <= ymin < ymax <= height):
                    raise ValueError("box VOC invalide")
                x, y = (xmin + xmax) / (2 * width), (ymin + ymax) / (2 * height)
                w, h = (xmax - xmin) / width, (ymax - ymin) / height
                lines.append(f"0 {x:.10g} {y:.10g} {w:.10g} {h:.10g}")
            stem = f"datacluster__{image.stem}"
            shutil.copy2(image, image_out / f"{stem}{image.suffix.lower()}")
            (label_out / f"{stem}.txt").write_text("\n".join(lines) + ("\n" if lines else ""), encoding="utf-8")
            accepted += 1
        except (KeyError, TypeError, ValueError, ET.ParseError) as exc:
            rejected.append({"xml": xml_path.as_posix(), "reason": str(exc)})
    (output / "conversion_report.json").write_text(
        json.dumps({"source": "datacluster_voc", "accepted": accepted, "rejected": rejected,
                    "class_mapping": {"fire": 0}, "seed": SEED}, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    print(f"VOC standardisé: {accepted}; rejetés: {len(rejected)}; sortie: {output}")


def merge_pools(inputs: list[Path], output: Path) -> None:
    """Fusionne des pools déjà standardisés; refuse toute collision de nom."""
    image_out, label_out = output / "images", output / "labels"
    if output.exists() and any(output.rglob("*")):
        raise ValueError(f"La sortie doit être vide: {output}")
    image_out.mkdir(parents=True)
    label_out.mkdir(parents=True)
    copied = 0
    for pool in inputs:
        images = {p.stem: p for p in files(pool / "images", IMAGE_EXTENSIONS)}
        labels = {p.stem: p for p in files(pool / "labels", {".txt"})}
        if set(images) != set(labels):
            raise ValueError(f"Pool non apparié: {pool}")
        for stem in sorted(images):
            if (image_out / images[stem].name).exists() or (label_out / f"{stem}.txt").exists():
                raise ValueError(f"Collision d'asset pendant la fusion: {stem}")
            parsed, errors = parse_yolo(labels[stem])
            if errors:
                raise ValueError(f"Label invalide dans {pool}: {errors[0]}")
            shutil.copy2(images[stem], image_out / images[stem].name)
            shutil.copy2(labels[stem], label_out / f"{stem}.txt")
            copied += 1
    print(f"Pools fusionnés: {copied} assets; sortie: {output}")


def build_grouped_splits(dataset: Path, pool: Path, output: Path, ratios: tuple[float, float, float]) -> None:
    """Crée train/val/test par groupe vérifié, dans une nouvelle racine candidate."""
    if output.exists() and any(output.rglob("*")):
        raise ValueError(f"La sortie doit être vide: {output}")
    if len(ratios) != 3 or any(value <= 0 for value in ratios) or abs(sum(ratios) - 1.0) > 1e-9:
        raise ValueError("Les ratios train/val/test doivent être positifs et totaliser 1")
    source_rows = read_csv(dataset / "metadata" / "sources.csv", SOURCES_HEADER)
    by_name = {Path(row["prepared_filename"]).name: row for row in source_rows}
    pool_images = {p.name: p for p in files(pool / "images", IMAGE_EXTENSIONS)}
    labels = {p.stem: p for p in files(pool / "labels", {".txt"})}
    if len(by_name) != len(source_rows):
        raise ValueError("prepared_filename doit être unique dans sources.csv")
    if set(pool_images) != set(by_name):
        missing_meta = sorted(set(pool_images) - set(by_name))
        missing_pool = sorted(set(by_name) - set(pool_images))
        raise ValueError(f"Catalogue/pool non concordants: metadata absente={len(missing_meta)}, asset absent={len(missing_pool)}")
    groups: dict[str, list[tuple[Path, Path, dict[str, str], tuple[int, int, int, int]]]] = defaultdict(list)
    for name, image in pool_images.items():
        row = by_name[name]
        if not row["license"].strip():
            raise ValueError(f"Licence absente: {name}")
        group = row["video_id"].strip() or row["scene_id"].strip()
        if not group:
            raise ValueError(f"scene_id/video_id absent: {name}")
        label = labels.get(image.stem)
        if label is None:
            raise ValueError(f"Label absent: {name}")
        parsed, errors = parse_yolo(label)
        if errors:
            raise ValueError(errors[0])
        classes = [int(line.split()[0]) for line in parsed]
        features = (1, int(0 in classes), int(1 in classes), int(not classes))
        groups[group].append((image, label, row, features))
    if len(groups) < 3:
        raise ValueError("Au moins trois groupes scène/vidéo sont nécessaires")
    totals = [sum(item[3][index] for assets in groups.values() for item in assets) for index in range(4)]
    split_names = ("train", "val", "test")
    targets = {split: [totals[i] * ratios[j] for i in range(4)] for j, split in enumerate(split_names)}
    current = {split: [0, 0, 0, 0] for split in split_names}
    assignments: dict[str, str] = {}
    rng = random.Random(SEED)
    ordered = list(groups.items())
    rng.shuffle(ordered)
    ordered.sort(key=lambda item: len(item[1]), reverse=True)
    for group, assets in ordered:
        vector = [sum(item[3][i] for item in assets) for i in range(4)]
        def score(split: str) -> float:
            return sum(((current[split][i] + vector[i] - targets[split][i]) / max(targets[split][i], 1.0)) ** 2 for i in range(4))
        chosen = min(split_names, key=lambda split: (score(split), current[split][0], split))
        assignments[group] = chosen
        current[chosen] = [current[chosen][i] + vector[i] for i in range(4)]
    if any(current[split][0] == 0 for split in split_names):
        raise ValueError(f"Split vide après allocation groupée: {current}")
    manifest_rows = []
    for split in split_names:
        (output / "images" / split).mkdir(parents=True, exist_ok=True)
        (output / "labels" / split).mkdir(parents=True, exist_ok=True)
    for group, assets in groups.items():
        split = assignments[group]
        for image, label, row, feature in assets:
            shutil.copy2(image, output / "images" / split / image.name)
            shutil.copy2(label, output / "labels" / split / label.name)
            manifest_rows.append({
                "asset_id": row["asset_id"], "prepared_filename": image.name, "split": split,
                "source_dataset": row["source_dataset"], "scene_id": row["scene_id"],
                "video_id": row["video_id"], "group_id": group, "sha256": sha256(image),
                "perceptual_hash": "", "is_positive": str(bool(feature[1] or feature[2])).lower(),
                "has_fire": str(bool(feature[1])).lower(), "has_smoke": str(bool(feature[2])).lower(),
                "seed": str(SEED), "notes": "grouped_split",
            })
    init_metadata(output)
    with (output / "metadata" / "sources.csv").open("w", encoding="utf-8", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=SOURCES_HEADER); writer.writeheader(); writer.writerows(source_rows)
    with (output / "metadata" / "split_manifest.csv").open("w", encoding="utf-8", newline="") as stream:
        writer = csv.DictWriter(stream, fieldnames=SPLIT_HEADER); writer.writeheader(); writer.writerows(manifest_rows)
    (output / "data.yaml").write_text(
        "path: .\ntrain: images/train\nval: images/val\ntest: images/test\n"
        "names:\n  0: fire\n  1: smoke\nnc: 2\n",
        encoding="utf-8",
    )
    (output / "split_summary.json").write_text(
        json.dumps({"seed": SEED, "ratios": ratios, "groups": len(groups), "allocation": current}, indent=2),
        encoding="utf-8",
    )
    print(f"Split groupé créé dans {output}: {current}")


def read_csv(path: Path, header: list[str]) -> list[dict[str, str]]:
    if not path.is_file():
        raise ValueError(f"Fichier absent: {path}")
    with path.open(encoding="utf-8-sig", newline="") as stream:
        reader = csv.DictReader(stream)
        if reader.fieldnames != header:
            raise ValueError(f"En-tête invalide: {path}")
        return list(reader)


def validate(dataset: Path) -> dict[str, object]:
    errors, warnings = [], []
    counts: dict[str, object] = {}
    yaml_path = dataset / "data.yaml"
    yaml_text = yaml_path.read_text(encoding="utf-8-sig", errors="replace") if yaml_path.is_file() else ""
    if "0: fire" not in yaml_text or "1: smoke" not in yaml_text:
        errors.append("data.yaml doit déclarer explicitement 0: fire et 1: smoke")
    if "/kaggle/" in yaml_text.replace("\\", "/").lower():
        errors.append("data.yaml contient un chemin Kaggle non portable")
    all_assets: set[str] = set()
    for split in ("train", "val", "test"):
        image_dir, label_dir = dataset / "images" / split, dataset / "labels" / split
        images = {p.stem: p for p in files(image_dir, IMAGE_EXTENSIONS)} if image_dir.exists() else {}
        labels = {p.stem: p for p in files(label_dir, {".txt"})} if label_dir.exists() else {}
        missing_labels, missing_images = sorted(set(images) - set(labels)), sorted(set(labels) - set(images))
        invalid, class_boxes, positive, negative = [], Counter(), 0, 0
        for stem, label in labels.items():
            lines, label_errors = parse_yolo(label)
            invalid.extend(label_errors)
            if lines:
                positive += 1
                class_boxes.update(int(line.split()[0]) for line in lines)
            elif not label.read_text(encoding="utf-8-sig", errors="replace").strip():
                negative += 1
        counts[split] = {"images": len(images), "labels": len(labels), "positive": positive,
                         "negative": negative, "boxes": dict(class_boxes), "invalid": len(invalid)}
        if not images:
            errors.append(f"split {split} vide")
        if missing_labels:
            errors.append(f"{split}: {len(missing_labels)} image(s) sans label")
        if missing_images:
            errors.append(f"{split}: {len(missing_images)} label(s) sans image")
        if invalid:
            errors.append(f"{split}: {len(invalid)} annotation(s) invalide(s)")
        duplicates = all_assets.intersection(images)
        if duplicates:
            errors.append(f"assets homonymes dans plusieurs splits: {len(duplicates)}")
        all_assets.update(images)
    try:
        sources = read_csv(dataset / "metadata" / "sources.csv", SOURCES_HEADER)
        manifest = read_csv(dataset / "metadata" / "split_manifest.csv", SPLIT_HEADER)
        read_csv(dataset / "metadata" / "annotation_review.csv", REVIEW_HEADER)
        if len(sources) != len(manifest):
            errors.append(f"sources.csv ({len(sources)}) et split_manifest.csv ({len(manifest)}) divergent")
        active_manifest = [row for row in manifest if row["split"] in {"train", "val", "test"}]
        active_names = {Path(row["prepared_filename"]).stem for row in active_manifest}
        if len(active_manifest) != len(all_assets) or active_names != all_assets:
            errors.append(
                f"manifest actif non concordant: {len(active_manifest)} lignes pour {len(all_assets)} assets"
            )
        if any(not row["license"].strip() for row in sources):
            errors.append("licence absente dans sources.csv")
        groups: dict[str, set[str]] = defaultdict(set)
        for row in active_manifest:
            group = row["group_id"].strip() or row["video_id"].strip() or row["scene_id"].strip()
            if not group:
                errors.append(f"groupe scène/vidéo absent pour {row['asset_id']}")
            else:
                groups[group].add(row["split"])
        leaked = {group: splits for group, splits in groups.items() if len(splits) > 1}
        if leaked:
            errors.append(f"{len(leaked)} groupe(s) scène/vidéo traversent plusieurs splits")
    except ValueError as exc:
        errors.append(str(exc))
    if not any("hard_negative" in row.get("notes", "").lower() for row in locals().get("sources", [])):
        warnings.append("aucun hard negative explicitement documenté")
    return {"valid": not errors, "seed": SEED, "classes": FINAL_CLASSES,
            "counts": counts, "errors": errors, "warnings": warnings}


def train(dataset: Path, experiments_path: Path) -> None:
    result = validate(dataset)
    report = ROOT / "reports" / "DATASET_VALIDATION.json"
    report.parent.mkdir(parents=True, exist_ok=True)
    report.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
    if not result["valid"]:
        raise SystemExit(f"Dataset invalide: entraînement refusé. Voir {report}")
    config = json.loads(experiments_path.read_text(encoding="utf-8"))
    if not all(model.get("weights", "").endswith(".pt") for model in config["models"]):
        raise ValueError("Chaque expérience doit partir de poids préentraînés .pt")
    try:
        from ultralytics import YOLO
    except ImportError as exc:
        raise SystemExit("Ultralytics absent. Installer l'environnement YOLO verrouillé avant entraînement.") from exc
    random.seed(int(config["seed"]))
    run_log = ROOT / "reports" / "TRAINING_RUNS.jsonl"
    for model_config in config["models"]:
        args = dict(config["training"])
        args.update(data=str((dataset / "data.yaml").resolve()), seed=int(config["seed"]),
                    name=model_config["name"], pretrained=True)
        with run_log.open("a", encoding="utf-8") as stream:
            stream.write(json.dumps({"date": date.today().isoformat(), "weights": model_config["weights"],
                                     "arguments": args}, ensure_ascii=False) + "\n")
        YOLO(model_config["weights"]).train(**args)


def require_valid_and_yolo(dataset: Path):
    result = validate(dataset)
    if not result["valid"]:
        raise SystemExit("Dataset invalide: opération YOLO refusée")
    try:
        from ultralytics import YOLO
    except ImportError as exc:
        raise SystemExit("Ultralytics absent") from exc
    return YOLO


def evaluate(weights: Path, dataset: Path, output: Path) -> None:
    YOLO = require_valid_and_yolo(dataset)
    metrics = YOLO(str(weights)).val(data=str((dataset / "data.yaml").resolve()), split="test",
                                     seed=SEED, deterministic=True)
    payload = {"weights": str(weights.resolve()), "split": "test", "seed": SEED,
               "metrics": {key: float(value) for key, value in metrics.results_dict.items()},
               "factory_test_completed": False,
               "limitations": ["Le test dataset ne remplace pas le test vidéo usine annoté."]}
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")


def test_media(weights: Path, sources: list[Path], output: Path, confidence: float) -> None:
    try:
        from ultralytics import YOLO
    except ImportError as exc:
        raise SystemExit("Ultralytics absent") from exc
    output.mkdir(parents=True, exist_ok=True)
    model, rows = YOLO(str(weights)), []
    for source in sources:
        for index, result in enumerate(model.predict(source=str(source), conf=confidence, stream=True,
                                                     save=True, project=str(output), name=source.stem)):
            classes = result.boxes.cls.int().cpu().tolist() if result.boxes is not None else []
            confidences = result.boxes.conf.cpu().tolist() if result.boxes is not None else []
            rows.append({"source": str(source.resolve()), "frame_index": index, "detections": len(classes),
                         "fire": classes.count(0), "smoke": classes.count(1),
                         "max_confidence": max(confidences, default=0.0)})
    (output / "media_predictions.json").write_text(
        json.dumps({"weights": str(weights.resolve()), "confidence": confidence, "frames": rows,
                    "ground_truth_available": False,
                    "warning": "Ces sorties ne constituent pas des métriques sans vérité terrain."},
                   ensure_ascii=False, indent=2), encoding="utf-8")


def export_model(weights: Path, formats: list[str], output: Path) -> None:
    try:
        from ultralytics import YOLO
    except ImportError as exc:
        raise SystemExit("Ultralytics absent") from exc
    output.mkdir(parents=True, exist_ok=True)
    exported = []
    for export_format in formats:
        path = Path(YOLO(str(weights)).export(format=export_format, imgsz=640))
        destination = output / path.name
        shutil.copy2(path, destination)
        exported.append(str(destination.resolve()))
    (output / "export_manifest.json").write_text(
        json.dumps({"source_weights": str(weights.resolve()), "classes": FINAL_CLASSES,
                    "exports": exported, "application_model_replaced": False}, indent=2), encoding="utf-8")


def select_model(evaluations: list[Path], output: Path) -> None:
    rows, eligible = [], []
    for path in evaluations:
        payload = json.loads(path.read_text(encoding="utf-8"))
        metrics = payload.get("metrics", {})
        map_value = metrics.get("metrics/mAP50-95(B)", metrics.get("map50_95"))
        row = {"report": str(path.resolve()), "weights": payload.get("weights"),
               "map50_95": map_value,
               "factory_test_completed": bool(payload.get("factory_test_completed")),
               "factory_false_alarms_per_hour": payload.get("factory_false_alarms_per_hour"),
               "factory_recall_fire": payload.get("factory_recall_fire")}
        rows.append(row)
        if (row["factory_test_completed"] and map_value is not None
                and row["factory_false_alarms_per_hour"] is not None
                and row["factory_recall_fire"] is not None):
            eligible.append(row)
    def dominates(a: dict[str, object], b: dict[str, object]) -> bool:
        av = (float(a["map50_95"]), float(a["factory_recall_fire"]), -float(a["factory_false_alarms_per_hour"]))
        bv = (float(b["map50_95"]), float(b["factory_recall_fire"]), -float(b["factory_false_alarms_per_hour"]))
        return all(x >= y for x, y in zip(av, bv)) and any(x > y for x, y in zip(av, bv))
    frontier = [row for row in eligible if not any(dominates(other, row) for other in eligible if other is not row)]
    selected = frontier[0] if len(frontier) == 1 else None
    limitations = []
    if not eligible:
        limitations.append("Aucun modèle éligible: mAP, rappel fire usine et fausses alertes/heure annotées sont requis.")
    elif len(frontier) > 1:
        limitations.append("Plusieurs modèles sont non dominés; une contrainte opérationnelle usine est requise pour les départager sans pondération arbitraire.")
    payload = {"selection_rule": "front de Pareto: maximiser mAP50-95 et rappel fire usine, minimiser fausses alertes/heure",
               "selected": selected, "pareto_frontier": frontier, "candidates": rows, "limitations": limitations}
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    if selected is None:
        raise SystemExit("Sélection automatique refusée: résultats usine insuffisants ou compromis non résolu")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dataset", type=Path, default=DEFAULT_DATASET)
    commands = parser.add_subparsers(dest="command", required=True)
    commands.add_parser("init-metadata")
    commands.add_parser("validate")
    d_fire = commands.add_parser("convert-d-fire")
    d_fire.add_argument("--output", type=Path, default=DEFAULT_DATASET / "staging" / "d_fire")
    voc = commands.add_parser("convert-voc")
    voc.add_argument("--output", type=Path, default=DEFAULT_DATASET / "staging" / "datacluster")
    merge = commands.add_parser("merge-pools")
    merge.add_argument("--inputs", nargs="+", type=Path, required=True)
    merge.add_argument("--output", type=Path, default=DEFAULT_DATASET / "staging" / "merged")
    split = commands.add_parser("build-splits")
    split.add_argument("--pool", type=Path, required=True)
    split.add_argument("--output", type=Path, required=True)
    split.add_argument("--ratios", nargs=3, type=float, default=(0.7, 0.15, 0.15))
    training = commands.add_parser("train")
    training.add_argument("--experiments", type=Path, default=ROOT / "configs" / "yolo_experiments.json")
    evaluation = commands.add_parser("evaluate")
    evaluation.add_argument("--weights", type=Path, required=True)
    evaluation.add_argument("--output", type=Path, required=True)
    media = commands.add_parser("test-media")
    media.add_argument("--weights", type=Path, required=True)
    media.add_argument("--sources", nargs="+", type=Path, required=True)
    media.add_argument("--output", type=Path, required=True)
    media.add_argument("--confidence", type=float, default=0.25)
    exporting = commands.add_parser("export")
    exporting.add_argument("--weights", type=Path, required=True)
    exporting.add_argument("--formats", nargs="+", choices=("onnx", "torchscript"), default=("onnx", "torchscript"))
    exporting.add_argument("--output", type=Path, required=True)
    selection = commands.add_parser("select-model")
    selection.add_argument("--evaluations", nargs="+", type=Path, required=True)
    selection.add_argument("--output", type=Path, default=ROOT / "reports" / "MODEL_SELECTION.json")
    args = parser.parse_args()
    dataset = args.dataset.resolve()
    if args.command == "init-metadata":
        init_metadata(dataset)
    elif args.command == "validate":
        result = validate(dataset)
        print(json.dumps(result, ensure_ascii=False, indent=2))
        raise SystemExit(0 if result["valid"] else 2)
    elif args.command == "convert-d-fire":
        convert_d_fire(dataset, args.output.resolve())
    elif args.command == "convert-voc":
        convert_voc(dataset, args.output.resolve())
    elif args.command == "merge-pools":
        merge_pools([path.resolve() for path in args.inputs], args.output.resolve())
    elif args.command == "build-splits":
        build_grouped_splits(dataset, args.pool.resolve(), args.output.resolve(), tuple(args.ratios))
    elif args.command == "train":
        train(dataset, args.experiments.resolve())
    elif args.command == "evaluate":
        evaluate(args.weights.resolve(), dataset, args.output.resolve())
    elif args.command == "test-media":
        test_media(args.weights.resolve(), [path.resolve() for path in args.sources], args.output.resolve(), args.confidence)
    elif args.command == "export":
        export_model(args.weights.resolve(), args.formats, args.output.resolve())
    elif args.command == "select-model":
        select_model([path.resolve() for path in args.evaluations], args.output.resolve())


if __name__ == "__main__":
    main()
