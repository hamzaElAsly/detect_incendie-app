"""Chargement unique du modèle et inférence commune PIL/OpenCV."""
from functools import lru_cache
import logging
from pathlib import Path
from typing import TypedDict

import cv2
import numpy as np
from PIL import Image
import torch
import torch.nn.functional as F
from torchvision import transforms

from config import PROJECT_ROOT
from logging_config import configure_logging
from model import CNN_Bottleneck

configure_logging()
logger = logging.getLogger(__name__)


class Prediction(TypedDict):
    label: str
    confidence: float
    is_fire: bool


class FireDetectionService:
    labels = ("fire", "no_fire")

    def __init__(self, model_path: Path | None = None):
        self.device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
        self.model_path = model_path or PROJECT_ROOT / "fire_cnn_bottleneck.pth"
        self.transform = transforms.Compose([
            transforms.Resize((224, 224)),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225]),
        ])
        self.model = CNN_Bottleneck(bottleneck_dim=64).to(self.device)
        self.model.load_state_dict(torch.load(self.model_path, map_location=self.device, weights_only=True))
        self.model.eval()
        logger.info("Modèle chargé sur %s depuis %s", self.device, self.model_path.name)

    def predict_pil(self, image: Image.Image) -> Prediction:
        tensor = self.transform(image.convert("RGB")).unsqueeze(0).to(self.device)
        with torch.inference_mode():
            probabilities = F.softmax(self.model(tensor), dim=1)
            confidence, predicted = torch.max(probabilities, dim=1)
        index = int(predicted.item())
        return {"label": self.labels[index], "confidence": float(confidence.item()), "is_fire": index == 0}

    def predict_frame(self, frame: np.ndarray) -> Prediction:
        if frame is None or frame.size == 0:
            raise ValueError("Frame OpenCV vide")
        rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
        return self.predict_pil(Image.fromarray(rgb))


@lru_cache(maxsize=1)
def get_fire_detection_service() -> FireDetectionService:
    return FireDetectionService()
