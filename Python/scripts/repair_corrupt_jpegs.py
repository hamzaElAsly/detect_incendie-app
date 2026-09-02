"""Back up and repair prepared JPEGs missing the EOI marker.

The original files are never deleted. Repaired candidates are written under
the dataset quarantine directory and must be promoted only after verification.
"""
from __future__ import annotations

import shutil
from pathlib import Path

from PIL import Image, ImageOps
from ultralytics.data.utils import check_image

ROOT = Path(__file__).resolve().parents[1]
DATASET = ROOT / "fire_factory_dataset"
BACKUP = ROOT / "repair_staging_20260823" / "originals"
REPAIRED = ROOT / "repair_staging_20260823" / "repaired"


def main() -> int:
    BACKUP.mkdir(parents=True, exist_ok=True)
    REPAIRED.mkdir(parents=True, exist_ok=True)
    found = 0
    verified = 0
    for source in sorted((DATASET / "images" / "train").glob("*.jp*g")):
        with source.open("rb") as stream:
            stream.seek(-2, 2)
            has_eoi = stream.read() == b"\xff\xd9"
        if has_eoi:
            continue
        found += 1
        backup = BACKUP / source.name
        repaired = REPAIRED / source.name
        if not backup.exists():
            shutil.copy2(source, backup)
        with Image.open(source) as image:
            ImageOps.exif_transpose(image).convert("RGB").save(
                repaired, "JPEG", subsampling=0, quality=100
            )
        check_image(str(repaired))
        verified += 1
        print(f"{source.relative_to(ROOT).as_posix()} -> {repaired.relative_to(ROOT).as_posix()}")
    print(f"repaired_candidates={found}")
    print(f"verified_repaired={verified}")
    print(f"backup={BACKUP}")
    print(f"repaired={REPAIRED}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
