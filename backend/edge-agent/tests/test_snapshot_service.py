from pathlib import Path
import tempfile
import unittest
import uuid
import os
import time

import cv2
import numpy as np

from src.snapshot_service import SnapshotService


class SnapshotServiceTests(unittest.TestCase):
    def test_valid_formats_resize_and_random_name(self):
        frame = np.zeros((100, 300, 3), dtype=np.uint8)
        for image_format in ("jpg", "png", "webp"):
            with tempfile.TemporaryDirectory() as directory:
                path = SnapshotService(Path(directory), image_format, max_width=100).save(frame, str(uuid.uuid4()))
                loaded = cv2.imread(str(path))
                self.assertEqual(loaded.shape[1], 100)
                self.assertNotIn("event", path.name)

    def test_invalid_format_empty_frame_and_size_limit(self):
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaises(ValueError): SnapshotService(Path(directory), "gif")
            service = SnapshotService(Path(directory), max_bytes=1)
            with self.assertRaises(ValueError): service.save(None, str(uuid.uuid4()))
            with self.assertRaises(ValueError): service.save(np.zeros((50, 50, 3), dtype=np.uint8), str(uuid.uuid4()))
            self.assertEqual(list(Path(directory).iterdir()), [])

    def test_retention_does_not_delete_queued_snapshot(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory); old = root / "old.jpg"; protected = root / "queued.jpg"
            old.write_bytes(b"x"); protected.write_bytes(b"x")
            timestamp = time.time() - 10 * 86400
            os.utime(old, (timestamp, timestamp)); os.utime(protected, (timestamp, timestamp))
            removed = SnapshotService(root).cleanup(7, {protected})
            self.assertEqual(removed, 1); self.assertFalse(old.exists()); self.assertTrue(protected.exists())

    def test_snapshot_requires_uuid4(self):
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaises(ValueError):
                SnapshotService(Path(directory)).save(np.zeros((20, 20, 3), dtype=np.uint8), str(uuid.uuid1()))


if __name__ == "__main__": unittest.main()
