"""Chargement unique, validation stricte et inférence commune PIL/OpenCV."""
from functools import lru_cache
import logging
from pathlib import Path
from typing import TypedDict

import numpy as np
from PIL import Image
from ultralytics import YOLO

from config import PROJECT_ROOT, settings
from logging_config import configure_logging
from services.image_preprocessing import opencv_frame_to_pil

configure_logging()
logger = logging.getLogger(__name__)


class BoundingBox(TypedDict):
    x1: float
    y1: float
    x2: float
    y2: float


class Detection(TypedDict):
    class_id: int
    class_name: str
    confidence: float

    bbox: BoundingBox


class Prediction(TypedDict, total=False):
    detections: list[Detection]
    has_detection: bool
    has_fire: bool
    has_smoke: bool
    max_confidence: float
    label: str
    confidence: float
    is_fire: bool


class FireDetectionService:
    def __init__(self, model_path: Path | None = None):
        self.model_path = self._resolve_model_path(model_path or settings.yolo_model_path)
        self.model = YOLO(str(self.model_path))
        self.classes = self._validate_class_mapping(self.model.names)
        logger.info("Modèle YOLO V2 chargé; classes=%s; path=%s", self.classes, self.model_path)

    @staticmethod
    def _resolve_model_path(configured_path: str | Path) -> Path:
        path = Path(configured_path)
        candidates = [path] if path.is_absolute() else [PROJECT_ROOT / path, PROJECT_ROOT.parent / path]
        for candidate in candidates:
            if candidate.is_file():
                return candidate.resolve()
        raise FileNotFoundError(
            f"Modèle YOLO introuvable: {configured_path}. "
            f"Vérifiez YOLO_MODEL_PATH depuis la racine du projet."
        )

    @staticmethod
    def _validate_class_mapping(names: object) -> dict[int, str]:
        if isinstance(names, dict):
            mapping = {int(index): str(name).strip().lower() for index, name in names.items()}
        elif isinstance(names, (list, tuple)):
            mapping = {index: str(name).strip().lower() for index, name in enumerate(names)}
        else:
            mapping = {}
        if mapping != {0: "fire", 1: "smoke"}:
            raise ValueError(f"Mapping du modèle invalide: attendu {{0: 'fire', 1: 'smoke'}}, reçu {mapping}")
        return mapping

    def predict_pil(self, image: Image.Image) -> Prediction:
        results = self.model.predict(image.convert("RGB"), conf=settings.fire_confidence_threshold, verbose=False)
        detections: list[Detection] = []
        for result in results:
            boxes = getattr(result, "boxes", None)
            if boxes is None:
                continue
            for class_id_value, confidence_value, box_value in zip(boxes.cls, boxes.conf, boxes.xyxy):
                class_id = int(class_id_value.item() if hasattr(class_id_value, "item") else class_id_value)
                confidence = float(confidence_value.item() if hasattr(confidence_value, "item") else confidence_value)
                coordinates = box_value.tolist() if hasattr(box_value, "tolist") else list(box_value)
                if class_id not in self.classes or confidence < settings.fire_confidence_threshold:
                    continue
                detections.append({
                    "class_id": class_id,
                    "class_name": self.classes[class_id],
                    "confidence": confidence,
                    "bbox": {key: float(value) for key, value in zip(("x1", "y1", "x2", "y2"), coordinates)},
                })
        detections.sort(key=lambda detection: detection["confidence"], reverse=True)
        max_confidence = detections[0]["confidence"] if detections else 0.0
        label = detections[0]["class_name"] if detections else "no_detection"
        result: Prediction = {
            "detections": detections,
            "has_detection": bool(detections),
            "has_fire": any(item["class_name"] == "fire" for item in detections),
            "has_smoke": any(item["class_name"] == "smoke" for item in detections),
            "max_confidence": max_confidence,
            "label": label,
            "confidence": max_confidence,
            "is_fire": any(item["class_name"] == "fire" for item in detections),
        }
        if settings.prediction_debug:
            logger.info("Prediction debug detections=%s threshold=%.4f", detections, settings.fire_confidence_threshold)
        return result

    def predict_frame(self, frame: np.ndarray) -> Prediction:
        return self.predict_pil(opencv_frame_to_pil(frame))


@lru_cache(maxsize=1)
def get_fire_detection_service() -> FireDetectionService:
    return FireDetectionService()
