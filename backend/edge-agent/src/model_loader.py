from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
import logging

logger = logging.getLogger(__name__)


@dataclass(frozen=True)
class ModelInfo:
    name: str
    version: str
    device: str
    business_validated: bool


class ModelLoader:
    EXPECTED = {0: "fire", 1: "smoke"}

    def __init__(self, path: Path, device: str = "cpu", mock: bool = False,
                 business_validated: bool = False, allow_generic: bool = False,
                 model_version: str = "v2"):
        self.path, self.requested_device, self.mock = path, device, mock
        self.business_validated = business_validated
        self.allow_generic = allow_generic
        self.model_version = model_version
        self._names: dict[int, str] = {}
        self._model = None
        self.device = device

    def load(self):
        if self.mock:
            return None
        if self._model is not None:
            return self._model
        if not self.path or not self.path.is_file():
            raise ValueError(f"YOLO model file not found: {self.path}")
        if self.path.suffix.lower() != ".pt":
            raise ValueError(f"YOLO model file must be a .pt file: {self.path}")
        if self.requested_device.startswith("cuda"):
            try:
                import torch
                if not torch.cuda.is_available():
                    logger.warning("cuda_unavailable fallback=cpu")
                    self.device = "cpu"
            except ImportError:
                self.device = "cpu"
        from ultralytics import YOLO
        model = YOLO(str(self.path))
        names = {int(k): str(v).lower() for k, v in dict(model.names).items()}
        if names != self.EXPECTED and not self.allow_generic:
            raise ValueError(f"YOLO model classes incompatible: expected exactly {self.EXPECTED}, got {names}")
        if names != self.EXPECTED:
            logger.warning("generic_model_loaded technical_test_only=true classes=%s", len(names))
        self._names = names
        self._model = model
        return model

    def warm_up(self, image_size: int = 640) -> None:
        model = self.load()
        if model is None:
            return
        import numpy as np
        sample = np.zeros((image_size, image_size, 3), dtype=np.uint8)
        try:
            model.predict(sample, device=self.device, imgsz=image_size, verbose=False)
        except RuntimeError as exc:
            if self.device.startswith("cuda") and "cuda" in str(exc).lower():
                logger.warning("cuda_warmup_failed fallback=cpu")
                self.device = "cpu"
                model.predict(sample, device="cpu", imgsz=image_size, verbose=False)
            else:
                raise

    @property
    def info(self) -> ModelInfo:
        name = "mock-detector" if self.mock else "generic-yolo" if self.allow_generic else "fire-smoke-yolo"
        return ModelInfo(name,
                         "mock" if self.mock else self.model_version, self.device,
                         self.business_validated and not self.mock and not self.allow_generic and self.path.name == "best.pt" and self.path.is_file())
