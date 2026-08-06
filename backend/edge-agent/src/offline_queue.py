from __future__ import annotations

from contextlib import closing
from dataclasses import dataclass
import json
from pathlib import Path
import sqlite3
import time

from .retry_policy import RetryPolicy


@dataclass(frozen=True)
class QueuedEvent:
    id: int
    event_uuid: str
    camera_id: str
    payload: dict
    snapshot_path: str | None
    status: str
    attempts: int


class QueueCapacityError(RuntimeError):
    pass


class OfflineQueue:
    STATUSES = {"pending", "sending", "retry", "sent", "failed"}

    def __init__(self, path: Path, max_items: int = 10_000, max_bytes: int = 1_073_741_824,
                 retry_policy: RetryPolicy | None = None):
        path.parent.mkdir(parents=True, exist_ok=True)
        self.path, self.max_items, self.max_bytes = path, max_items, max_bytes
        self.retry_policy = retry_policy or RetryPolicy(max_retries=5, max_seconds=300)
        with closing(self._connect()) as db:
            db.execute("PRAGMA journal_mode=WAL")
            db.execute("""CREATE TABLE IF NOT EXISTS queued_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT, event_uuid TEXT NOT NULL UNIQUE,
                camera_id TEXT NOT NULL, payload_json TEXT NOT NULL, snapshot_path TEXT,
                status TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0,
                next_attempt_at REAL NOT NULL, last_error TEXT,
                created_at REAL NOT NULL, updated_at REAL NOT NULL)""")
            db.execute("CREATE INDEX IF NOT EXISTS queue_ready ON queued_events(status, next_attempt_at, id)")
            legacy = db.execute("SELECT 1 FROM sqlite_master WHERE type='table' AND name='events'").fetchone()
            if legacy:
                db.execute("""INSERT OR IGNORE INTO queued_events
                    (event_uuid,camera_id,payload_json,snapshot_path,status,attempts,next_attempt_at,last_error,created_at,updated_at)
                    SELECT event_uuid,'',payload,snapshot_path,'retry',attempts,next_attempt,NULL,created_at,?
                    FROM events""", (time.time(),))
            db.execute("UPDATE queued_events SET status='retry', next_attempt_at=?, updated_at=? WHERE status='sending'", (time.time(), time.time()))
            db.commit()

    def enqueue(self, event_uuid: str, payload: dict, snapshot_path: str | None = None, camera_id: str = "") -> bool:
        encoded = json.dumps(payload, separators=(",", ":"), allow_nan=False)
        now = time.time()
        with closing(self._connect()) as db:
            db.execute("BEGIN IMMEDIATE")
            active = db.execute("SELECT COUNT(*) FROM queued_events WHERE status IN ('pending','sending','retry')").fetchone()[0]
            db_size = sum(p.stat().st_size for p in self.path.parent.glob(self.path.name + "*"))
            snapshots = db.execute("SELECT snapshot_path FROM queued_events WHERE snapshot_path IS NOT NULL").fetchall()
            snapshot_size = sum(Path(p[0]).stat().st_size for p in snapshots if Path(p[0]).is_file())
            new_snapshot_size = Path(snapshot_path).stat().st_size if snapshot_path and Path(snapshot_path).is_file() else 0
            if active >= self.max_items or db_size + snapshot_size + new_snapshot_size + len(encoded.encode()) > self.max_bytes:
                db.rollback()
                raise QueueCapacityError("Offline queue capacity reached")
            cursor = db.execute("""INSERT OR IGNORE INTO queued_events
                (event_uuid,camera_id,payload_json,snapshot_path,status,attempts,next_attempt_at,created_at,updated_at)
                VALUES (?,?,?,?, 'pending',0,?,?,?)""",
                (event_uuid, camera_id, encoded, snapshot_path, now, now, now))
            db.commit()
            return cursor.rowcount == 1

    def peek_ready(self, now: float | None = None, limit: int = 100) -> list[QueuedEvent]:
        with closing(self._connect()) as db:
            rows = db.execute("""SELECT id,event_uuid,camera_id,payload_json,snapshot_path,status,attempts
                FROM queued_events WHERE status IN ('pending','retry') AND next_attempt_at<=?
                ORDER BY id LIMIT ?""", (time.time() if now is None else now, limit)).fetchall()
        return [QueuedEvent(r[0], r[1], r[2], json.loads(r[3]), r[4], r[5], r[6]) for r in rows]

    def ready(self, now: float | None = None) -> list[QueuedEvent]:
        return self.peek_ready(now)

    def mark_sending(self, event_uuid: str) -> None:
        self._update(event_uuid, "status='sending', updated_at=?", (time.time(),))

    def mark_retry(self, event_uuid: str, error: str, now: float | None = None) -> None:
        event = self._find(event_uuid)
        attempts = event.attempts + 1
        if attempts > self.retry_policy.max_retries:
            self.mark_failed(event_uuid, error)
            return
        current = time.time() if now is None else now
        self._update(event_uuid, "status='retry', attempts=?, next_attempt_at=?, last_error=?, updated_at=?",
                     (attempts, current + self.retry_policy.delay(attempts), error[:1000], current))

    def retry(self, event_uuid: str, attempts: int | None = None, now: float | None = None) -> None:
        self.mark_retry(event_uuid, "retryable_error", now)

    def mark_sent(self, event_uuid: str) -> None:
        self.acknowledge(event_uuid)

    def mark_failed(self, event_uuid: str, error: str) -> None:
        self._update(event_uuid, "status='failed', last_error=?, updated_at=?", (error[:1000], time.time()))

    def acknowledge(self, event_uuid: str) -> None:
        with closing(self._connect()) as db:
            row = db.execute("SELECT snapshot_path FROM queued_events WHERE event_uuid=?", (event_uuid,)).fetchone()
            db.execute("DELETE FROM queued_events WHERE event_uuid=?", (event_uuid,))
            db.commit()
        if row and row[0]:
            Path(row[0]).unlink(missing_ok=True)

    def count(self, active_only: bool = False) -> int:
        where = " WHERE status IN ('pending','sending','retry')" if active_only else ""
        with closing(self._connect()) as db:
            return int(db.execute(f"SELECT COUNT(*) FROM queued_events{where}").fetchone()[0])

    def size_bytes(self) -> int:
        size = sum(p.stat().st_size for p in self.path.parent.glob(self.path.name + "*"))
        with closing(self._connect()) as db:
            paths = db.execute("SELECT snapshot_path FROM queued_events WHERE snapshot_path IS NOT NULL").fetchall()
        return size + sum(Path(p[0]).stat().st_size for p in paths if Path(p[0]).is_file())

    def snapshot_paths(self) -> set[Path]:
        with closing(self._connect()) as db:
            rows = db.execute("SELECT snapshot_path FROM queued_events WHERE snapshot_path IS NOT NULL").fetchall()
        return {Path(row[0]).resolve() for row in rows}

    def cleanup(self, older_than_seconds: float = 86400) -> int:
        threshold = time.time() - older_than_seconds
        with closing(self._connect()) as db:
            rows = db.execute("SELECT snapshot_path FROM queued_events WHERE status IN ('sent','failed') AND updated_at<? AND snapshot_path IS NOT NULL", (threshold,)).fetchall()
            cursor = db.execute("DELETE FROM queued_events WHERE status IN ('sent','failed') AND updated_at<?", (threshold,))
            db.commit()
        for row in rows:
            Path(row[0]).unlink(missing_ok=True)
        return cursor.rowcount

    def _find(self, event_uuid: str) -> QueuedEvent:
        with closing(self._connect()) as db:
            row = db.execute("SELECT id,event_uuid,camera_id,payload_json,snapshot_path,status,attempts FROM queued_events WHERE event_uuid=?", (event_uuid,)).fetchone()
        if not row:
            raise KeyError(event_uuid)
        return QueuedEvent(row[0], row[1], row[2], json.loads(row[3]), row[4], row[5], row[6])

    def _update(self, event_uuid: str, clause: str, values: tuple) -> None:
        with closing(self._connect()) as db:
            db.execute(f"UPDATE queued_events SET {clause} WHERE event_uuid=?", (*values, event_uuid))
            db.commit()

    def _connect(self) -> sqlite3.Connection:
        return sqlite3.connect(self.path, timeout=10)
