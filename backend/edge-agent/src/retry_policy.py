from __future__ import annotations

from dataclasses import dataclass
from email.utils import parsedate_to_datetime
import random
from datetime import datetime, timezone


@dataclass(frozen=True)
class RetryPolicy:
    max_retries: int = 5
    initial_seconds: float = 1.0
    max_seconds: float = 60.0

    def delay(self, attempt: int, retry_after: str | None = None) -> float:
        if retry_after:
            try:
                return min(self.max_seconds, max(0.0, float(retry_after)))
            except ValueError:
                try:
                    parsed = parsedate_to_datetime(retry_after)
                    return min(self.max_seconds, max(0.0, (parsed - datetime.now(timezone.utc)).total_seconds()))
                except (TypeError, ValueError, OverflowError):
                    pass
        base = min(self.max_seconds, self.initial_seconds * (2 ** max(0, attempt - 1)))
        return min(self.max_seconds, base + random.uniform(0, min(1.0, base * .2)))
