import tempfile
from pathlib import Path
import unittest
import sqlite3
import json

from src.offline_queue import OfflineQueue, QueueCapacityError


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

    def test_sending_is_recovered_after_restart_and_failures_are_terminal(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "queue.db"
            queue = OfflineQueue(path)
            queue.enqueue("a", {"event_uuid": "a"})
            queue.mark_sending("a")
            recovered = OfflineQueue(path)
            self.assertEqual(recovered.ready()[0].status, "retry")
            recovered.mark_failed("a", "invalid payload")
            self.assertEqual(recovered.ready(), [])
            self.assertEqual(recovered.count(), 1)

    def test_cleanup_removes_failed_snapshot(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory); snapshot = root / "failed.jpg"; snapshot.write_bytes(b"jpeg")
            queue = OfflineQueue(root / "queue.db")
            queue.enqueue("a", {"event_uuid": "a"}, str(snapshot)); queue.mark_failed("a", "invalid")
            self.assertEqual(queue.cleanup(older_than_seconds=-1), 1)
            self.assertFalse(snapshot.exists()); self.assertEqual(queue.count(), 0)

    def test_legacy_phase4_queue_is_migrated_without_loss(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "queue.db"
            db = sqlite3.connect(path)
            db.execute("CREATE TABLE events (event_uuid TEXT PRIMARY KEY, payload TEXT NOT NULL, snapshot_path TEXT, attempts INTEGER NOT NULL DEFAULT 0, next_attempt REAL NOT NULL, created_at REAL NOT NULL)")
            db.execute("INSERT INTO events VALUES (?,?,?,?,?,?)", ("legacy", json.dumps({"event_uuid": "legacy"}), None, 2, 0, 1))
            db.commit(); db.close()
            queue = OfflineQueue(path)
            item = queue.peek_ready()[0]
            self.assertEqual((item.event_uuid, item.attempts, item.status), ("legacy", 2, "retry"))

    def test_capacity_limit_rejects_without_discarding_fifo_head(self):
        with tempfile.TemporaryDirectory() as directory:
            queue = OfflineQueue(Path(directory) / "queue.db", max_items=1)
            queue.enqueue("first", {"event_uuid": "first"})
            with self.assertRaises(QueueCapacityError): queue.enqueue("second", {"event_uuid": "second"})
            self.assertEqual([item.event_uuid for item in queue.peek_ready()], ["first"])


if __name__ == "__main__":
    unittest.main()
