from __future__ import annotations

import argparse
from datetime import datetime, timezone
import logging
from pathlib import Path
import threading
import time
import uuid

from .api_client import EdgeApiClient
from .camera_stream import CameraStream
from .config import EdgeConfig, ROOT
from .inference_service import InferenceService
from .heartbeat_service import HeartbeatService
from .logging_config import configure_logging
from .offline_queue import OfflineQueue
from .snapshot_service import SnapshotService
from .temporal_filter import DetectionState, TemporalDetectionFilter

logger = logging.getLogger(__name__)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dry-run", action="store_true", default=False)
    args = parser.parse_args()
    config = EdgeConfig()
    configure_logging(ROOT / "storage" / "edge-agent.log")
    if not args.dry_run:
        config.validate_runtime()
    if not config.model_path.is_file():
        raise SystemExit("Model absent: training/evaluation required before Edge startup")

    detector = InferenceService(config.model_path, config.device)
    temporal = TemporalDetectionFilter(
        {"fire": config.fire_threshold, "smoke": config.smoke_threshold},
        config.minimum_positive_frames,
        config.confirmation_window_seconds,
        config.cooldown_seconds,
        config.end_timeout_seconds,
    )
    api = EdgeApiClient(config.api_base_url, config.api_client_token, config.camera_id, config.verify_tls)
    queue = OfflineQueue(ROOT / "storage" / "queue" / "events.db")
    snapshots = SnapshotService(ROOT / "storage" / "snapshots")
    stream = CameraStream(config.source)
    heartbeat = HeartbeatService(api, config.heartbeat_interval, config.model_path.stem)
    if not args.dry_run:
        threading.Thread(target=heartbeat.run, name="edge-heartbeat", daemon=True).start()
    frame_interval, last_inference = 1 / config.inference_fps, 0.0
    for frame in stream.frames():
        if not args.dry_run:
            for queued in queue.ready():
                try:
                    snapshot_path = Path(queued.snapshot_path) if queued.snapshot_path else None
                    api.send_detection(queued.payload, snapshot_path)
                    queue.acknowledge(queued.event_uuid)
                except Exception as exc:
                    queue.retry(queued.event_uuid, queued.attempts + 1)
                    logger.warning(
                        "retry deferred event_uuid=%s attempts=%s error=%s",
                        queued.event_uuid,
                        queued.attempts + 1,
                        exc.__class__.__name__,
                    )
        if time.monotonic() - last_inference < frame_interval:
            continue
        last_inference = time.monotonic()
        for detection in detector.predict(frame):
            decision = temporal.observe(detection.class_name, detection.confidence)
            if decision.state is not DetectionState.CONFIRMED:
                continue
            event_uuid = str(uuid.uuid4())
            snapshot = snapshots.save(frame, event_uuid)
            payload = {
                "event_uuid": event_uuid,
                "detected_at": datetime.now(timezone.utc).isoformat(),
                "class": detection.class_name,
                "confidence": detection.confidence,
                "bounding_box": detection.bounding_box,
                "frame_width": frame.shape[1],
                "frame_height": frame.shape[0],
                "model_name": "fire-smoke-yolo",
                "model_version": config.model_path.stem,
                "inference_ms": detection.inference_ms,
                "confirmation_frames": config.minimum_positive_frames,
            }
            if args.dry_run:
                logger.info("dry_run confirmed event_uuid=%s class=%s", event_uuid, detection.class_name)
                snapshot.unlink(missing_ok=True)
                continue
            try:
                api.send_detection(payload, snapshot)
                snapshot.unlink(missing_ok=True)
            except Exception as exc:
                queue.enqueue(event_uuid, payload, str(snapshot))
                logger.warning("detection queued event_uuid=%s error=%s", event_uuid, exc.__class__.__name__)


if __name__ == "__main__":
    main()
