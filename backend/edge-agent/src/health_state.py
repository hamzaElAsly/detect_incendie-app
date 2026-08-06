from __future__ import annotations

from dataclasses import dataclass
from collections import deque
from enum import Enum
import time


class StreamState(str, Enum):
    STARTING = "starting"
    CONNECTING = "connecting"
    ONLINE = "online"
    DEGRADED = "degraded"
    OFFLINE = "offline"
    RECONNECTING = "reconnecting"
    STOPPED = "stopped"


@dataclass
class HealthState:
    state: StreamState = StreamState.STARTING
    frames: int = 0
    reconnects: int = 0
    started_at: float = 0.0
    last_frame_at: float | None = None
    offline_since: float | None = None
    offline_seconds_total: float = 0.0
    error_code: str | None = None
    error_message: str | None = None
    _frame_times: deque[float] | None = None

    def __post_init__(self) -> None:
        if not self.started_at:
            self.started_at = time.monotonic()
        if self._frame_times is None:
            self._frame_times = deque(maxlen=60)

    @property
    def uptime_seconds(self) -> int:
        return int(time.monotonic() - self.started_at)

    @property
    def observed_fps(self) -> float:
        times = self._frame_times
        if times is None or len(times) < 2:
            return 0.0
        elapsed = times[-1] - times[0]
        return (len(times) - 1) / elapsed if elapsed > 0 else 0.0

    @property
    def offline_duration_seconds(self) -> float:
        current = time.time() - self.offline_since if self.offline_since else 0.0
        return self.offline_seconds_total + current

    def record_frame(self, captured_at: float) -> None:
        self.frames += 1
        self.last_frame_at = captured_at
        self._frame_times.append(time.monotonic())

    def mark_offline(self) -> None:
        if self.offline_since is None:
            self.offline_since = time.time()

    def mark_online(self) -> None:
        if self.offline_since is not None:
            self.offline_seconds_total += max(0.0, time.time() - self.offline_since)
            self.offline_since = None
