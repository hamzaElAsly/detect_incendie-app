from __future__ import annotations

from datetime import datetime, timezone
import logging
import threading

logger = logging.getLogger(__name__)


class HeartbeatService:
    def __init__(self, api, interval: int, model_version: str):
        self.api, self.interval, self.model_version = api, interval, model_version
        self.stop_event = threading.Event()

    def run(self) -> None:
        while not self.stop_event.wait(self.interval):
            try:
                self.api.send_heartbeat({
                    "status": "online",
                    "sent_at": datetime.now(timezone.utc).isoformat(),
                    "model_version": self.model_version,
                })
            except Exception as exc:
                logger.warning("heartbeat failed error=%s", exc.__class__.__name__)

    def stop(self) -> None:
        self.stop_event.set()
