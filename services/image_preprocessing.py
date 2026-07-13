"""Métadonnées et preprocessing uniques pour toutes les interfaces."""
import json
from functools import lru_cache
from pathlib import Path
from typing import Any

import cv2
import numpy as np
from PIL import Image
from torchvision import transforms

from config import PROJECT_ROOT

METADATA_PATH = PROJECT_ROOT / "model_metadata.json"


@lru_cache(maxsize=1)
def load_model_metadata(path: Path = METADATA_PATH) -> dict[str, Any]:
    metadata = json.loads(path.read_text(encoding="utf-8"))
    classes = metadata.get("classes")
    mapping = metadata.get("class_to_idx")
    if not isinstance(classes, list) or len(classes) != 2:
        raise ValueError("model_metadata.json doit définir exactement deux classes.")
    if mapping != {label: index for index, label in enumerate(classes)}:
        raise ValueError("class_to_idx ne correspond pas à l'ordre de classes.")
    return metadata


@lru_cache(maxsize=1)
def get_inference_transform():
    metadata = load_model_metadata()
    normalization = metadata["normalization"]
    size = int(metadata["image_size"])
    return transforms.Compose([
        transforms.Resize((size, size)),
        transforms.ToTensor(),
        transforms.Normalize(mean=normalization["mean"], std=normalization["std"]),
    ])


def opencv_frame_to_pil(frame: np.ndarray) -> Image.Image:
    if frame is None or frame.size == 0:
        raise ValueError("Frame OpenCV vide")
    if frame.ndim != 3 or frame.shape[2] != 3:
        raise ValueError(f"Frame OpenCV BGR attendue avec 3 canaux, shape reçue: {frame.shape}")
    rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
    return Image.fromarray(rgb_frame)
