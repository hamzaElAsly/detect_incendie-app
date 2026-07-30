from __future__ import annotations

from pathlib import Path
import uuid

import cv2


class SnapshotService:
    def __init__(self, directory: Path):
        self.directory = directory
        directory.mkdir(parents=True, exist_ok=True)

    def save(self, frame, event_uuid: str) -> Path:
        uuid.UUID(event_uuid)
        path = self.directory / f"{uuid.uuid4().hex}.jpg"
        if not cv2.imwrite(str(path), frame):
            raise OSError("Unable to write snapshot")
        return path
