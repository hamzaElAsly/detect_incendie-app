import tempfile
from pathlib import Path
import unittest

from src.offline_queue import OfflineQueue


class OfflineQueueTests(unittest.TestCase):
    def test_queue_is_persistent_ordered_and_idempotent(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "queue.db"
            queue = OfflineQueue(path)
            self.assertTrue(queue.enqueue("a", {"event_uuid": "a"}))
            self.assertFalse(queue.enqueue("a", {"event_uuid": "a"}))
            self.assertTrue(queue.enqueue("b", {"event_uuid": "b"}))
            self.assertEqual([event.event_uuid for event in OfflineQueue(path).ready()], ["a", "b"])

    def test_acknowledge_removes_snapshot_and_retry_defers(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            snapshot = root / "snapshot.jpg"
            snapshot.write_bytes(b"jpeg")
            queue = OfflineQueue(root / "queue.db")
            queue.enqueue("a", {"event_uuid": "a"}, str(snapshot))
            queue.retry("a", 1, now=100)
            self.assertEqual(queue.ready(now=100), [])
            queue.acknowledge("a")
            self.assertFalse(snapshot.exists())


if __name__ == "__main__":
    unittest.main()
