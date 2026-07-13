import csv
import json
from pathlib import Path
import tempfile
import unittest

from scripts.fire_dataset_pipeline import (
    REVIEW_HEADER,
    SOURCES_HEADER,
    SPLIT_HEADER,
    build_grouped_splits,
    parse_yolo,
    select_model,
)


class DatasetPipelineTests(unittest.TestCase):
    def test_d_fire_class_remap_is_explicit(self):
        with tempfile.TemporaryDirectory() as directory:
            label = Path(directory) / "sample.txt"
            label.write_text("0 0.5 0.5 0.2 0.2\n1 0.4 0.4 0.1 0.1\n", encoding="utf-8")
            lines, errors = parse_yolo(label, {0: 1, 1: 0})
            self.assertEqual(errors, [])
            self.assertEqual([line.split()[0] for line in lines], ["1", "0"])

    def test_zero_sized_box_is_rejected(self):
        with tempfile.TemporaryDirectory() as directory:
            label = Path(directory) / "sample.txt"
            label.write_text("0 0.5 0.5 0 0.2\n", encoding="utf-8")
            lines, errors = parse_yolo(label)
            self.assertEqual(lines, [])
            self.assertIn("largeur/hauteur nulle", errors[0])

    def test_grouped_split_never_separates_a_group(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            dataset, pool, output = root / "dataset", root / "pool", root / "candidate"
            (dataset / "metadata").mkdir(parents=True)
            (pool / "images").mkdir(parents=True)
            (pool / "labels").mkdir(parents=True)
            rows = []
            for index in range(6):
                name = f"asset_{index}.jpg"
                (pool / "images" / name).write_bytes(f"image-{index}".encode())
                (pool / "labels" / f"asset_{index}.txt").write_text(
                    "0 0.5 0.5 0.2 0.2\n" if index % 2 else "", encoding="utf-8")
                rows.append(dict.fromkeys(SOURCES_HEADER, "") | {
                    "asset_id": f"id-{index}", "original_filename": name,
                    "prepared_filename": name, "source_dataset": "fixture",
                    "source_type": "image", "source_path": name, "license": "test-only",
                    "scene_id": f"scene-{index // 2}", "date_added": "2026-07-13",
                })
            with (dataset / "metadata" / "sources.csv").open("w", encoding="utf-8", newline="") as stream:
                writer = csv.DictWriter(stream, fieldnames=SOURCES_HEADER); writer.writeheader(); writer.writerows(rows)
            for name, header in (("split_manifest.csv", SPLIT_HEADER), ("annotation_review.csv", REVIEW_HEADER)):
                with (dataset / "metadata" / name).open("w", encoding="utf-8", newline="") as stream:
                    csv.writer(stream).writerow(header)
            build_grouped_splits(dataset, pool, output, (0.5, 0.25, 0.25))
            with (output / "metadata" / "split_manifest.csv").open(encoding="utf-8") as stream:
                manifest = list(csv.DictReader(stream))
            groups = {}
            for row in manifest:
                groups.setdefault(row["group_id"], set()).add(row["split"])
            self.assertTrue(groups)
            self.assertTrue(all(len(splits) == 1 for splits in groups.values()))
            self.assertEqual({row["split"] for row in manifest}, {"train", "val", "test"})

    def test_selection_requires_and_uses_factory_test(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            reports = []
            for name, score, factory, recall, false_alarms in (
                ("a", 0.7, True, 0.80, 2.0), ("b", 0.8, False, 0.90, 0.5), ("c", 0.75, True, 0.85, 1.0)
            ):
                path = root / f"{name}.json"
                path.write_text(json.dumps({"weights": f"{name}.pt", "metrics": {"map50_95": score},
                                            "factory_test_completed": factory,
                                            "factory_recall_fire": recall,
                                            "factory_false_alarms_per_hour": false_alarms}), encoding="utf-8")
                reports.append(path)
            output = root / "selection.json"
            select_model(reports, output)
            selected = json.loads(output.read_text(encoding="utf-8"))["selected"]
            self.assertEqual(selected["weights"], "c.pt")


if __name__ == "__main__":
    unittest.main()
