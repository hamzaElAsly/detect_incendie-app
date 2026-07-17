"""Sauvegarde atomique des captures dans un chemin relatif au projet."""
from datetime import datetime
import logging
from pathlib import Path

import cv2
import numpy as np
from PIL import Image

from config import PROJECT_ROOT
from logging_config import configure_logging

configure_logging()
logger = logging.getLogger(__name__)
DETECTIONS_DIR = PROJECT_ROOT / "storage" / "detections"


def save_capture(image: np.ndarray | Image.Image, now: datetime | None = None) -> str:
    DETECTIONS_DIR.mkdir(parents=True, exist_ok=True)
    timestamp = now or datetime.now()
    filename = f"fire_{timestamp:%Y%m%d_%H%M%S_%f}.jpg"
    destination = DETECTIONS_DIR / filename
    if isinstance(image, Image.Image):
        image.convert("RGB").save(destination, format="JPEG", quality=92)
    elif not cv2.imwrite(str(destination), image):
        raise OSError(f"Échec de sauvegarde de {filename}")
    relative = destination.relative_to(PROJECT_ROOT).as_posix()
    logger.info("Image sauvegardée: %s", relative)
    return relative
