from __future__ import annotations

import logging
import threading

from .utils import utc_now

logger = logging.getLogger(__name__)


class HeartbeatService:
    def __init__(self, api, interval: int, payload_factory):
        self.api, self.interval, self.payload_factory = api, interval, payload_factory
        self.stop_event, self.wake_event = threading.Event(), threading.Event()
        self._send_lock = threading.Lock()
        self._thread: threading.Thread | None = None

    def start(self) -> None:
        if self._thread and self._thread.is_alive():
            return
        self._thread = threading.Thread(target=self.run, name="edge-heartbeat", daemon=True)
        self._thread.start()

    def run(self) -> None:
        self._send()
        while not self.stop_event.is_set():
            self.wake_event.wait(self.interval)
            self.wake_event.clear()
            if self.stop_event.is_set():
                break
            self._send()

    def notify_state_change(self) -> None:
        self.wake_event.set()

    def _send(self, status_override: str | None = None, retry: bool = True) -> None:
        with self._send_lock:
            try:
                payload = self.payload_factory()
                payload["sent_at"] = utc_now()
                if status_override:
                    payload["status"] = status_override
                self.api.send_heartbeat(payload, retry=retry)
            except Exception as exc:
                logger.warning("heartbeat_failed error=%s", exc.__class__.__name__)

    def stop(self, final: bool = True) -> None:
        if final:
            self._send("error", retry=False)
        self.stop_event.set()
        self.wake_event.set()
        if self._thread:
            self._thread.join(timeout=2)
