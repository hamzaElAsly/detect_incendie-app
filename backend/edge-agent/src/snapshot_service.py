from __future__ import annotations

from datetime import datetime, timedelta, timezone
from pathlib import Path
import uuid

import cv2


class SnapshotService:
    EXTENSIONS = {"jpg": ".jpg", "jpeg": ".jpg", "png": ".png", "webp": ".webp"}

    def __init__(self, directory: Path, image_format: str = "jpg", quality: int = 85,
                 max_width: int = 1920, max_bytes: int = 10 * 1024 * 1024):
        self.directory, self.image_format = directory, image_format.lower()
        self.quality, self.max_width, self.max_bytes = quality, max_width, max_bytes
        if self.image_format not in self.EXTENSIONS:
            raise ValueError("Unsupported snapshot format")
        directory.mkdir(parents=True, exist_ok=True)

    def save(self, frame, event_uuid: str) -> Path:
        if uuid.UUID(event_uuid).version != 4:
            raise ValueError("event_uuid must be UUIDv4")
        if frame is None or getattr(frame, "size", 0) == 0:
            raise ValueError("Snapshot frame is empty")
        height, width = frame.shape[:2]
        if width > self.max_width:
            ratio = self.max_width / width
            frame = cv2.resize(frame, (self.max_width, max(1, int(height * ratio))))
        suffix = self.EXTENSIONS[self.image_format]
        path = self.directory / f"{uuid.uuid4().hex}{suffix}"
        params = []
        if suffix == ".jpg":
            params = [cv2.IMWRITE_JPEG_QUALITY, self.quality]
        elif suffix == ".webp":
            params = [cv2.IMWRITE_WEBP_QUALITY, self.quality]
        if not cv2.imwrite(str(path), frame, params):
            raise OSError("Unable to write snapshot")
        if path.stat().st_size > self.max_bytes:
            path.unlink(missing_ok=True)
            raise ValueError("Snapshot exceeds 10 MiB")
        return path

    def cleanup(self, retention_days: int, protected_paths: set[Path] | None = None) -> int:
        threshold = datetime.now(timezone.utc) - timedelta(days=retention_days)
        protected = {path.resolve() for path in (protected_paths or set())}
        removed = 0
        for path in self.directory.iterdir():
            if path.is_file() and path.resolve() not in protected and datetime.fromtimestamp(path.stat().st_mtime, timezone.utc) < threshold:
                path.unlink(missing_ok=True)
                removed += 1
        return removed
