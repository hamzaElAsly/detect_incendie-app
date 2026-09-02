"""Check every prepared dataset image with Pillow and OpenCV.

The YOLO cache scanner is stricter than the label validator.  This audit makes
that check explicit and records the exact files without modifying the dataset.
"""
from __future__ import annotations

import csv
from pathlib import Path
import sys

import cv2
from PIL import Image
from ultralytics.data.utils import verify_image_label

ROOT = Path(__file__).resolve().parents[1]
DATASET = ROOT / (sys.argv[1] if len(sys.argv) > 1 else "fire_factory_dataset")
SPLIT = sys.argv[2] if len(sys.argv) > 2 else "train"
REPORT = ROOT / "reports" / "IMAGE_INTEGRITY_CURRENT.csv"
EXTENSIONS = {".jpg", ".jpeg", ".png", ".bmp", ".webp", ".tif", ".tiff"}


def main() -> int:
    rows: list[dict[str, str]] = []
    checked = 0
    image_root = DATASET / "images" if SPLIT == "all" else DATASET / "images" / SPLIT
    for image_path in sorted(image_root.rglob("*")):
        if not image_path.is_file() or image_path.suffix.lower() not in EXTENSIONS:
            continue
        checked += 1
        status, detail = "ok", ""
        try:
            with Image.open(image_path) as image:
                image.verify()
            if cv2.imread(str(image_path), cv2.IMREAD_COLOR) is None:
                status, detail = "corrupt", "opencv_decode_failed"
            else:
                label_path = DATASET / "labels" / image_path.parent.name / f"{image_path.stem}.txt"
                checked_pair = verify_image_label(
                    (str(image_path), str(label_path), "train: ", False, 2, 0, 3, False)
                )
                if checked_pair[0] is None:
                    status, detail = "corrupt", checked_pair[-1]
        except Exception as exc:  # Pillow/OpenCV expose format-specific errors.
            status, detail = "corrupt", f"{type(exc).__name__}: {exc}"
        rows.append({
            "path": image_path.relative_to(ROOT).as_posix(),
            "split": image_path.parent.name,
            "status": status,
            "detail": detail,
        })

    bad = [row for row in rows if row["status"] != "ok"]
    print(f"checked={checked}")
    print(f"corrupt={len(bad)}")
    for row in bad:
        print(f"{row['path']}\t{row['detail']}")
    try:
        REPORT.parent.mkdir(parents=True, exist_ok=True)
        with REPORT.open("w", encoding="utf-8", newline="") as stream:
            writer = csv.DictWriter(stream, fieldnames=("path", "split", "status", "detail"))
            writer.writeheader()
            writer.writerows(rows)
        print(f"report={REPORT}")
    except PermissionError:
        print(f"report_write=permission_denied:{REPORT}")
    return 1 if bad else 0


if __name__ == "__main__":
    raise SystemExit(main())
