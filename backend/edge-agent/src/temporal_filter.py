from __future__ import annotations

from collections import defaultdict, deque
from dataclasses import dataclass
from enum import Enum
import time


class DetectionState(str, Enum):
    REJECTED = "rejected"
    CANDIDATE = "candidate"
    CONFIRMED = "confirmed"
    COOLDOWN = "cooldown"
    ENDED = "ended"


@dataclass(frozen=True)
class FilterResult:
    state: DetectionState
    class_name: str
    positive_frames: int


class TemporalDetectionFilter:
    def __init__(
        self,
        thresholds: dict[str, float],
        minimum_positive_frames: int,
        confirmation_window_seconds: float,
        cooldown_seconds: float,
        end_timeout_seconds: float,
    ):
        self.thresholds = thresholds
        self.minimum = minimum_positive_frames
        self.window = confirmation_window_seconds
        self.cooldown = cooldown_seconds
        self.end_timeout = end_timeout_seconds
        self._positives: dict[str, deque[float]] = defaultdict(deque)
        self._confirmed_at: dict[str, float] = {}
        self._last_positive: dict[str, float] = {}

    def reset(self, class_name: str | None = None) -> None:
        if class_name is None:
            self._positives.clear()
            self._confirmed_at.clear()
            self._last_positive.clear()
            return
        self._positives.pop(class_name, None)
        self._confirmed_at.pop(class_name, None)
        self._last_positive.pop(class_name, None)

    def observe(self, class_name: str, confidence: float, observed_at: float | None = None) -> FilterResult:
        now = observed_at if observed_at is not None else time.monotonic()
        if class_name not in self.thresholds or confidence < self.thresholds[class_name]:
            return self._expire_or_reject(class_name, now)
        self._last_positive[class_name] = now
        confirmed = self._confirmed_at.get(class_name)
        if confirmed is not None and now < confirmed + self.cooldown:
            return FilterResult(DetectionState.COOLDOWN, class_name, 0)
        frames = self._positives[class_name]
        frames.append(now)
        while frames and frames[0] < now - self.window:
            frames.popleft()
        if len(frames) < self.minimum:
            return FilterResult(DetectionState.CANDIDATE, class_name, len(frames))
        self._confirmed_at[class_name] = now
        frames.clear()
        return FilterResult(DetectionState.CONFIRMED, class_name, self.minimum)

    def _expire_or_reject(self, class_name: str, now: float) -> FilterResult:
        last = self._last_positive.get(class_name)
        if last is not None and now - last >= self.end_timeout:
            self._positives[class_name].clear()
            self._confirmed_at.pop(class_name, None)
            self._last_positive.pop(class_name, None)
            return FilterResult(DetectionState.ENDED, class_name, 0)
        return FilterResult(DetectionState.REJECTED, class_name, 0)
