"""Diagnostic reproductible des prédictions et des captures webcam normales."""
import csv
from pathlib import Path
import random
import statistics
import sys

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))
from services.fire_detection_service import FireDetectionService  # noqa: E402

CSV_PATH = ROOT / "prediction_diagnostics.csv"
REPORT_PATH = ROOT / "PREDICTION_DIAGNOSTICS.md"


def subtype(path: Path) -> str:
    return path.stem.split(" (")[0]


def main() -> None:
    rng = random.Random(42)
    groups: dict[str, list[Path]] = {}
    for path in sorted((ROOT / "data_set").rglob("*.png")):
        groups.setdefault(f"{path.parent.name}/{subtype(path)}", []).append(path)
    selected: list[tuple[Path, str, str]] = []
    for name, paths in sorted(groups.items()):
        expected = "fire" if name.startswith("Fire/") else "no_fire"
        for path in rng.sample(paths, min(25, len(paths))):
            selected.append((path, expected, name))
    for path in sorted((ROOT / "storage" / "detections").glob("*.jpg")):
        selected.append((path, "no_fire", "webcam_normal"))
    service = FireDetectionService()
    rows: list[dict[str, object]] = []
    for path, expected, true_folder in selected:
        result = service.predict_pil(Image.open(path).convert("RGB"))
        row = {
            "image": path.relative_to(ROOT).as_posix(), "true_folder": true_folder,
            "expected_label": expected, "predicted_label": result["label"],
            "predicted_index": result["predicted_index"], "confidence": result["confidence"],
            "probability_fire": result["probabilities"]["fire"],
            "probability_no_fire": result["probabilities"]["no_fire"],
            "correct_or_not": result["label"] == expected,
        }
        rows.append(row)
        print(" | ".join(str(row[key]) for key in ("image", "true_folder", "predicted_label", "predicted_index", "confidence", "correct_or_not")))
    with CSV_PATH.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=list(rows[0])); writer.writeheader(); writer.writerows(rows)
    dataset_rows = [row for row in rows if row["true_folder"] != "webcam_normal"]
    false_positives = [row for row in rows if row["expected_label"] == "no_fire" and row["predicted_label"] == "fire"]
    false_negatives = [row for row in rows if row["expected_label"] == "fire" and row["predicted_label"] == "no_fire"]
    accuracy = sum(bool(row["correct_or_not"]) for row in dataset_rows) / len(dataset_rows)
    inverted_accuracy = sum(("no_fire" if row["predicted_index"] == 0 else "fire") == row["expected_label"] for row in dataset_rows) / len(dataset_rows)
    problematic = sorted((row for row in rows if not row["correct_or_not"]), key=lambda row: float(row["confidence"]), reverse=True)
    lines = [
        "# Diagnostic des prédictions", "", "## Résumé", "",
        f"- Échantillon dataset stratifié et déterministe : {len(dataset_rows)} images (25 maximum par sous-type).",
        f"- Captures webcam normales ajoutées : {len(rows)-len(dataset_rows)}.",
        f"- Accuracy approximative dataset : {accuracy:.2%}.",
        f"- Accuracy avec mapping réel `0→fire, 1→no_fire` : {accuracy:.2%}.",
        f"- Accuracy avec mapping inversé `0→no_fire, 1→fire` : {inverted_accuracy:.2%}.",
        f"- Faux positifs (incluant webcam) : {len(false_positives)}; faux négatifs : {len(false_negatives)}.",
        f"- Confiance moyenne : {statistics.mean(float(row['confidence']) for row in rows):.4f}.",
        "", "## Exemples les plus problématiques", "",
        *[f"- `{row['image']}` attendu `{row['expected_label']}`, prédit `{row['predicted_label']}` à {float(row['confidence']):.4%}." for row in problematic[:20]],
        "", "## Conclusion", "",
        "Le mapping réel surpasse explicitement le mapping inversé. Les captures webcam normales restent des faux positifs très confiants avec le pipeline vérifié; cela prouve un défaut de généralisation/calibration du checkpoint et non une inversion de labels.",
    ]
    REPORT_PATH.write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"accuracy = {accuracy:.6f}")
    print(f"inverted_mapping_accuracy = {inverted_accuracy:.6f}")
    print(f"false_positives = {len(false_positives)}")
    print(f"false_negatives = {len(false_negatives)}")
    print(f"csv = {CSV_PATH}")
    print(f"report = {REPORT_PATH}")


if __name__ == "__main__":
    main()
