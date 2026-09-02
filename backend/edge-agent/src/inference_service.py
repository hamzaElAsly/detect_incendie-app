from __future__ import annotations

from dataclasses import dataclass
import time

from .model_loader import ModelLoader


@dataclass(frozen=True)
class Detection:
    class_name: str
    class_id: int
    confidence: float
    bounding_box: dict[str, float]
    inference_ms: float


def best_detections_by_class(detections: list[Detection]) -> list[Detection]:
    best: dict[str, Detection] = {}
    for detection in detections:
        previous = best.get(detection.class_name)
        if previous is None or detection.confidence > previous.confidence:
            best[detection.class_name] = detection
    return list(best.values())


class InferenceService:
    def __init__(self, model_path=None, device: str = "cpu", image_size: int = 640, mock: bool = False,
                 loader: ModelLoader | None = None, warmup: bool = False,
                 business_validated: bool = False, allow_generic: bool = False,
                 model_version: str = "v2", confidence_floor: float | None = None):
        self.loader = loader or ModelLoader(model_path, device, mock, business_validated, allow_generic, model_version)
        self.model = self.loader.load()
        self.image_size = image_size
        self.confidence_floor = confidence_floor
        if warmup:
            self.loader.warm_up(image_size)

    def predict(self, frame) -> list[Detection]:
        if self.model is None:
            return []
        started = time.perf_counter()
        kwargs = {"device": self.loader.device, "imgsz": self.image_size, "verbose": False}
        if self.confidence_floor is not None:
            kwargs["conf"] = self.confidence_floor
        try:
            result = self.model.predict(frame, **kwargs)[0]
        except RuntimeError as exc:
            if self.loader.device.startswith("cuda") and "cuda" in str(exc).lower():
                self.loader.device = "cpu"
                kwargs["device"] = "cpu"
                result = self.model.predict(frame, **kwargs)[0]
            else:
                raise
        elapsed = (time.perf_counter() - started) * 1000
        height, width = frame.shape[:2]
        detections = []
        for box in result.boxes:
            class_id = int(box.cls.item())
            name = str(result.names[class_id]).lower()
            if name not in {"fire", "smoke"}:
                continue
            x1, y1, x2, y2 = (float(v) for v in box.xyxy[0].tolist())
            confidence = float(box.conf.item())
            import math
            if not all(math.isfinite(value) for value in (x1, y1, x2, y2, confidence)):
                continue
            x1, y1, x2, y2 = max(0., x1), max(0., y1), min(float(width), x2), min(float(height), y2)
            if x2 <= x1 or y2 <= y1:
                continue
            detections.append(Detection(name, class_id, min(1., max(0., confidence)),
                {"x": x1 / width, "y": y1 / height, "width": (x2-x1)/width, "height": (y2-y1)/height}, elapsed))
        return detections
