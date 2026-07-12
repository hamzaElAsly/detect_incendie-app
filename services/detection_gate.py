"""Confirmation multi-frame et cooldown pour éviter les doublons."""
from dataclasses import dataclass
from datetime import datetime, timedelta


@dataclass
class DetectionGate:
    threshold: float
    required_positive_frames: int
    cooldown_seconds: int
    positive_frames: int = 0
    last_detection_at: datetime | None = None

    def observe(self, is_fire: bool, confidence: float, now: datetime | None = None) -> bool:
        observed_at = now or datetime.now()
        positive = is_fire and confidence >= self.threshold
        self.positive_frames = self.positive_frames + 1 if positive else 0
        if self.positive_frames < self.required_positive_frames:
            return False
        if self.last_detection_at and observed_at < self.last_detection_at + timedelta(seconds=self.cooldown_seconds):
            return False
        self.last_detection_at = observed_at
        self.positive_frames = 0
        return True
