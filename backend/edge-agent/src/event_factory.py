from __future__ import annotations

from dataclasses import dataclass
import math
import re
import uuid

from .inference_service import Detection
from .utils import utc_now


@dataclass(frozen=True)
class EdgeEvent:
    event_uuid: str
    payload: dict


class EventFactoryError(ValueError):
    pass


def create_event(detection: Detection, frame_width: int, frame_height: int,
                 model_name: str, model_version: str, confirmation_frames: int) -> EdgeEvent:
    if detection.class_name not in {"fire", "smoke"}:
        raise EventFactoryError("Detection class must be fire or smoke")
    if not math.isfinite(detection.confidence) or not 0 <= detection.confidence <= 1:
        raise EventFactoryError("Detection confidence must be between 0 and 1")
    box = detection.bounding_box
    if set(box) != {"x", "y", "width", "height"} or not all(isinstance(value, (int, float)) and math.isfinite(value) for value in box.values()):
        raise EventFactoryError("Bounding box must contain finite x, y, width, and height")
    if box["x"] < 0 or box["y"] < 0 or box["width"] <= 0 or box["height"] <= 0 or box["x"] + box["width"] > 1 or box["y"] + box["height"] > 1:
        raise EventFactoryError("Bounding box must be non-empty and remain inside normalized bounds")
    if not 16 <= frame_width <= 16384 or not 16 <= frame_height <= 16384:
        raise EventFactoryError("Frame dimensions do not satisfy the Laravel contract")
    if not all(re.fullmatch(r"[A-Za-z0-9._-]{1,100}", value) for value in (model_name, model_version)):
        raise EventFactoryError("Model name and version do not satisfy the Laravel contract")
    if not math.isfinite(detection.inference_ms) or not 0 <= detection.inference_ms <= 600000:
        raise EventFactoryError("Inference duration is invalid")
    if not 1 <= confirmation_frames <= 1000:
        raise EventFactoryError("Confirmation frame count is invalid")
    event_uuid = str(uuid.uuid4())
    payload = {
        "event_uuid": event_uuid, "detected_at": utc_now(), "class": detection.class_name,
        "confidence": detection.confidence, "bounding_box": box,
        "frame_width": frame_width, "frame_height": frame_height,
        "model_name": model_name, "model_version": model_version,
        "inference_ms": detection.inference_ms, "confirmation_frames": confirmation_frames,
    }
    return EdgeEvent(event_uuid, payload)
