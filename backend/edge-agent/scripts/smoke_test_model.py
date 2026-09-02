from __future__ import annotations

import argparse
import json
import os
from pathlib import Path
import sys

import cv2

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from src.inference_service import InferenceService  # noqa: E402
from src.settings import PROJECT_ROOT, ROOT  # noqa: E402


IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp", ".bmp"}


def env_text(names: tuple[str, ...], default: str) -> str:
    for name in names:
        value = os.getenv(name)
        if value is not None and value.strip() != "":
            return value.strip()
    return default


def resolve_path(value: str) -> Path:
    path = Path(value)
    if path.is_absolute():
        return path
    project_path = PROJECT_ROOT / path
    if project_path.exists() or path.parts[:1] in {("Python",), ("backend",)}:
        return project_path
    return ROOT / path


def read_first_frame(source: Path):
    if source.suffix.lower() in IMAGE_EXTENSIONS:
        frame = cv2.imread(str(source))
        if frame is None:
            raise ValueError(f"Unable to read image source: {source}")
        return frame, "image"

    capture = cv2.VideoCapture(str(source), cv2.CAP_FFMPEG)
    try:
        ok, frame = capture.read()
    finally:
        capture.release()
    if not ok or frame is None:
        raise ValueError(f"Unable to read first video frame: {source}")
    return frame, "video"


def main() -> None:
    parser = argparse.ArgumentParser(description="Run one local YOLO V2 inference smoke test.")
    parser.add_argument("--source", required=True, help="Local image or video path.")
    parser.add_argument("--model-path", default=env_text(("YOLO_MODEL_PATH", "EDGE_MODEL_PATH"), "Python/models/fire_smoke_v2/best.pt"))
    parser.add_argument("--model-version", default=env_text(("YOLO_MODEL_VERSION", "EDGE_MODEL_VERSION"), "v2"))
    parser.add_argument("--device", default=env_text(("EDGE_DEVICE",), "cpu"))
    parser.add_argument("--image-size", type=int, default=int(env_text(("EDGE_MODEL_IMAGE_SIZE",), "640")))
    parser.add_argument("--fire-threshold", type=float, default=float(env_text(("FIRE_CONF_THRESHOLD", "EDGE_FIRE_THRESHOLD"), "0.30")))
    parser.add_argument("--smoke-threshold", type=float, default=float(env_text(("SMOKE_CONF_THRESHOLD", "EDGE_SMOKE_THRESHOLD"), "0.30")))
    parser.add_argument("--allow-empty", action="store_true", help="Exit 0 even if the model returns no fire/smoke detections.")
    args = parser.parse_args()

    source = resolve_path(args.source)
    model_path = resolve_path(args.model_path)
    if not source.is_file():
        raise SystemExit(f"Smoke test source not found: {source}")

    frame, source_kind = read_first_frame(source)
    service = InferenceService(
        model_path,
        args.device.lower(),
        args.image_size,
        model_version=args.model_version,
        confidence_floor=min(args.fire_threshold, args.smoke_threshold),
    )
    detections = service.predict(frame)
    payload = {
        "model_loaded": True,
        "model_path": str(model_path),
        "model_version": service.loader.info.version,
        "source": str(source),
        "source_kind": source_kind,
        "frame_width": int(frame.shape[1]),
        "frame_height": int(frame.shape[0]),
        "thresholds": {"fire": args.fire_threshold, "smoke": args.smoke_threshold},
        "detections": [
            {
                "class": detection.class_name,
                "class_id": detection.class_id,
                "confidence": detection.confidence,
                "bounding_box": detection.bounding_box,
                "inference_ms": detection.inference_ms,
            }
            for detection in detections
        ],
    }
    print(json.dumps(payload, indent=2))
    if not detections and not args.allow_empty:
        raise SystemExit("No fire/smoke detection returned by the model for this source.")


if __name__ == "__main__":
    main()
