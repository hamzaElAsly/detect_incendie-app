from __future__ import annotations

import logging
import signal
import time
from pathlib import Path

from .api_client import ApiError, EdgeApiClient
from .camera_stream import CameraStream
from .event_factory import create_event
from .heartbeat_service import HeartbeatService
from .inference_service import InferenceService, best_detections_by_class
from .logging_config import configure_logging
from .offline_queue import OfflineQueue, QueueCapacityError
from .retry_policy import RetryPolicy
from .settings import EdgeSettings
from .snapshot_service import SnapshotService
from .temporal_filter import DetectionState, TemporalDetectionFilter
from .utils import utc_now
from datetime import datetime, timezone

logger = logging.getLogger(__name__)


class EdgeAgent:
    VERSION = "1.0.0"

    def __init__(self, settings: EdgeSettings):
        settings.validate()
        self.settings = settings
        retry = RetryPolicy(settings.api_max_retries, settings.api_retry_initial_seconds, settings.api_retry_max_seconds)
        self.api = EdgeApiClient(settings.api_base_url, settings.api_client_token, settings.camera_id,
            settings.verify_tls, settings.api_connect_timeout_seconds, settings.api_read_timeout_seconds, retry,
            heartbeat_timeout=settings.heartbeat_timeout_seconds)
        self.queue = OfflineQueue(settings.queue_db_path, settings.queue_max_events, settings.queue_max_bytes, retry)
        self.snapshots = SnapshotService(settings.snapshot_dir, settings.snapshot_format,
            settings.snapshot_quality, settings.snapshot_max_width)
        self.snapshots.cleanup(settings.snapshot_retention_days, self.queue.snapshot_paths())
        self.stream = CameraStream(settings.source, settings.source_type, settings.rtsp_connect_timeout_seconds,
            settings.rtsp_read_timeout_seconds, settings.reconnect_initial_seconds, settings.reconnect_max_seconds,
            settings.frame_width, settings.frame_height)
        self.detector = InferenceService(settings.model_path, settings.device, settings.model_image_size,
            settings.mock_detector, warmup=settings.model_warmup,
            business_validated=settings.model_business_validated,
            allow_generic=settings.allow_generic_model)
        self.temporal = TemporalDetectionFilter({"fire": settings.fire_threshold, "smoke": settings.smoke_threshold},
            settings.minimum_positive_frames, settings.confirmation_window_seconds,
            settings.cooldown_seconds, settings.end_timeout_seconds)
        self.last_detection_at: str | None = None
        self.last_reconnect_count = 0
        self.heartbeat = HeartbeatService(self.api, settings.heartbeat_interval_seconds, self._heartbeat_payload)
        self.stream.on_state_change = self.heartbeat.notify_state_change
        self.stopping = False
        self._stopped = False

    def run(self) -> None:
        logger.info("agent_start agent_id=%s dry_run=%s mock_detector=%s model_business_validated=%s",
                    self.settings.agent_id, self.settings.dry_run, self.settings.mock_detector,
                    self.detector.loader.info.business_validated)
        if not self.settings.dry_run:
            self.heartbeat.start()
        interval, last_inference = 1 / self.settings.inference_fps, 0.0
        try:
            for frame_result in self.stream.results():
                if self.stopping:
                    break
                frame = frame_result.frame
                if not self.settings.dry_run:
                    self.flush_queue()
                if self.stream.health.reconnects != self.last_reconnect_count:
                    self.last_reconnect_count = self.stream.health.reconnects
                    self.temporal.reset()
                    if not self.settings.dry_run:
                        self.heartbeat.notify_state_change()
                if time.monotonic() - last_inference < interval:
                    continue
                last_inference = time.monotonic()
                detections = {item.class_name: item for item in best_detections_by_class(self.detector.predict(frame))}
                for class_name in ("fire", "smoke"):
                    detection = detections.get(class_name)
                    decision = self.temporal.observe(class_name, detection.confidence if detection else 0.0)
                    if decision.state is not DetectionState.CONFIRMED:
                        continue
                    if detection is None:
                        continue
                    event = create_event(detection, frame_result.width, frame_result.height, self.detector.loader.info.name,
                                         self.detector.loader.info.version, decision.positive_frames)
                    snapshot = self.snapshots.save(frame, event.event_uuid)
                    self.last_detection_at = event.payload["detected_at"]
                    if self.settings.dry_run:
                        logger.info("dry_run_detection event_uuid=%s class=%s", event.event_uuid, detection.class_name)
                        snapshot.unlink(missing_ok=True)
                    else:
                        self._deliver_or_queue(event.event_uuid, event.payload, snapshot)
        finally:
            self.stop()

    def flush_queue(self) -> None:
        for queued in self.queue.peek_ready(limit=20):
            self.queue.mark_sending(queued.event_uuid)
            try:
                snapshot = Path(queued.snapshot_path) if queued.snapshot_path else None
                self.api.send_detection(queued.payload, snapshot)
                self.queue.mark_sent(queued.event_uuid)
            except ApiError as exc:
                if exc.retryable:
                    self.queue.mark_retry(queued.event_uuid, str(exc))
                else:
                    self.queue.mark_failed(queued.event_uuid, str(exc))
            except Exception as exc:
                self.queue.mark_retry(queued.event_uuid, exc.__class__.__name__)

    def _deliver_or_queue(self, event_uuid: str, payload: dict, snapshot: Path) -> None:
        try:
            self.api.send_detection(payload, snapshot)
            snapshot.unlink(missing_ok=True)
        except ApiError as exc:
            if not exc.retryable:
                logger.error("detection_rejected event_uuid=%s status=%s", event_uuid, exc.status_code)
                snapshot.unlink(missing_ok=True)
                return
            self._enqueue(event_uuid, payload, snapshot, exc)
        except Exception as exc:
            self._enqueue(event_uuid, payload, snapshot, exc)

    def _enqueue(self, event_uuid: str, payload: dict, snapshot: Path, exc: Exception) -> None:
        try:
            self.queue.enqueue(event_uuid, payload, str(snapshot), self.settings.camera_id)
            logger.warning("detection_queued event_uuid=%s error=%s", event_uuid, exc.__class__.__name__)
        except QueueCapacityError:
            logger.critical("queue_capacity_reached event_uuid=%s snapshot_retained=%s", event_uuid, snapshot.name)

    def _heartbeat_payload(self) -> dict:
        health = self.stream.health
        state = "online" if health.state.value == "online" else "degraded" if health.state.value in {"degraded", "connecting", "reconnecting"} else "error"
        resolution = f"{self.stream._resolution[0]}x{self.stream._resolution[1]}" if self.stream._resolution else None
        last_frame_at = datetime.fromtimestamp(health.last_frame_at, timezone.utc).isoformat().replace("+00:00", "Z") if health.last_frame_at else None
        return {"status": state, "fps": round(health.observed_fps, 3), "resolution": resolution,
                "last_frame_at": last_frame_at,
                "edge_agent_version": self.VERSION, "model_version": self.detector.loader.info.version,
                "error_code": health.error_code, "error_message": health.error_message}

    def stop(self) -> None:
        if self._stopped:
            return
        self.stopping = True
        self._stopped = True
        self.stream.stop()
        if not self.settings.dry_run:
            self.heartbeat.stop()
        self.api.close()
        logger.info("agent_stopped frames=%s reconnects=%s offline_seconds=%.3f uptime_seconds=%s",
                    self.stream.health.frames, self.stream.health.reconnects,
                    self.stream.health.offline_duration_seconds, self.stream.health.uptime_seconds)

    def request_stop(self) -> None:
        self.stopping = True
        self.stream.stop()


def main() -> None:
    settings = EdgeSettings()
    configure_logging(settings.log_dir / "edge-agent.log", settings.log_level,
                      settings.log_max_bytes, settings.log_backup_count)
    agent = EdgeAgent(settings)
    for sig in (signal.SIGINT, signal.SIGTERM):
        signal.signal(sig, lambda *_: agent.request_stop())
    agent.run()


if __name__ == "__main__":
    main()
