from __future__ import annotations

from dataclasses import dataclass
import time


@dataclass(frozen=True)
class Detection:
    class_name: str
    confidence: float
    bounding_box: dict[str, float]
    inference_ms: float


class InferenceService:
    def __init__(self, model_path, device: str):
        from ultralytics import YOLO

        self.model = YOLO(str(model_path))
        self.device = device
        names = self.model.names
        if names.get(0) != "fire" or names.get(1) != "smoke":
            raise ValueError(f"Expected classes fire=0 and smoke=1, got {names}")

    def predict(self, frame) -> list[Detection]:
        started = time.perf_counter()
        result = self.model.predict(frame, device=self.device, verbose=False)[0]
        elapsed = (time.perf_counter() - started) * 1000
        detections = []
        height, width = frame.shape[:2]
        for box in result.boxes:
            class_name = result.names[int(box.cls.item())]
            x1, y1, x2, y2 = box.xyxy[0].tolist()
            detections.append(Detection(
                class_name,
                float(box.conf.item()),
                {"x": x1 / width, "y": y1 / height, "width": (x2 - x1) / width, "height": (y2 - y1) / height},
                elapsed,
            ))
        return detections
