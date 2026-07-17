"""Chargement unique, validation stricte et inférence commune PIL/OpenCV."""
from functools import lru_cache
import logging
from pathlib import Path
from typing import TypedDict

import numpy as np
from PIL import Image
import torch
import torch.nn.functional as F

from config import PROJECT_ROOT, settings
from logging_config import configure_logging
from model import CNN_Bottleneck
from services.image_preprocessing import (
    get_inference_transform,
    load_model_metadata,
    opencv_frame_to_pil,
)

configure_logging()
logger = logging.getLogger(__name__)


class Prediction(TypedDict):
    label: str
    confidence: float
    is_fire: bool
    predicted_index: int
    probabilities: dict[str, float]


class FireDetectionService:
    def __init__(self, model_path: Path | None = None):
        self.metadata = load_model_metadata()
        self.classes = tuple(self.metadata["classes"])
        self.device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
        self.model_path = model_path or PROJECT_ROOT / self.metadata["checkpoint"]
        self.transform = get_inference_transform()
        architecture = self.metadata["architecture"]
        self.model = CNN_Bottleneck(bottleneck_dim=int(architecture["bottleneck_dim"])).to(self.device)
        checkpoint = torch.load(self.model_path, map_location=self.device, weights_only=True)
        if not isinstance(checkpoint, dict) or "classifier.weight" not in checkpoint:
            raise ValueError("Checkpoint invalide: state_dict CNN_Bottleneck attendu.")
        expected_outputs = int(architecture["num_outputs"])
        if checkpoint["classifier.weight"].shape[0] != expected_outputs:
            raise ValueError("Checkpoint incompatible avec le nombre de classes des métadonnées.")
        self.model.load_state_dict(checkpoint, strict=True)
        self.model.eval()
        logger.info("Modèle chargé strictement sur %s; classes=%s", self.device, self.classes)

    def predict_pil(self, image: Image.Image) -> Prediction:
        tensor = self.transform(image.convert("RGB")).unsqueeze(0).to(self.device)
        with torch.inference_mode():
            logits = self.model(tensor)
            if logits.ndim != 2 or logits.shape != (1, len(self.classes)):
                raise RuntimeError(f"Sortie modèle inattendue: {tuple(logits.shape)}")
            probabilities_tensor = F.softmax(logits, dim=1)
            predicted_index = int(probabilities_tensor.argmax(dim=1).item())
            confidence = float(probabilities_tensor[0, predicted_index].item())
        probabilities = {
            label: float(probabilities_tensor[0, index].item())
            for index, label in enumerate(self.classes)
        }
        label = self.classes[predicted_index]
        result: Prediction = {
            "label": label,
            "confidence": confidence,
            "is_fire": label == "fire",
            "predicted_index": predicted_index,
            "probabilities": probabilities,
        }
        if settings.prediction_debug:
            logger.info(
                "Prediction debug logits=%s probabilities=%s index=%s label=%s confidence=%.6f "
                "classes=%s tensor_shape=%s tensor_min=%.6f tensor_max=%.6f tensor_mean=%.6f device=%s",
                logits.detach().cpu().tolist(), probabilities, predicted_index, label, confidence,
                self.classes, tuple(tensor.shape), float(tensor.min()), float(tensor.max()),
                float(tensor.mean()), self.device,
            )
        return result

    def predict_frame(self, frame: np.ndarray) -> Prediction:
        return self.predict_pil(opencv_frame_to_pil(frame))


@lru_cache(maxsize=1)
def get_fire_detection_service() -> FireDetectionService:
    return FireDetectionService()
