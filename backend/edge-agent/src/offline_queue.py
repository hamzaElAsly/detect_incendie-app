from __future__ import annotations

from dataclasses import dataclass
from contextlib import closing
import json
from pathlib import Path
import random
import sqlite3
import time


@dataclass(frozen=True)
class QueuedEvent:
    event_uuid: str
    payload: dict
    snapshot_path: str | None
    attempts: int


class OfflineQueue:
    def __init__(self, path: Path, max_items: int = 10_000):
        path.parent.mkdir(parents=True, exist_ok=True)
        self.path, self.max_items = path, max_items
        with closing(self._connect()) as db:
            db.execute(
                "CREATE TABLE IF NOT EXISTS events (event_uuid TEXT PRIMARY KEY, payload TEXT NOT NULL, "
                "snapshot_path TEXT, attempts INTEGER NOT NULL DEFAULT 0, next_attempt REAL NOT NULL, "
                "created_at REAL NOT NULL)"
            )
            db.commit()

    def enqueue(self, event_uuid: str, payload: dict, snapshot_path: str | None = None) -> bool:
        with closing(self._connect()) as db:
            if db.execute("SELECT COUNT(*) FROM events").fetchone()[0] >= self.max_items:
                return False
            cursor = db.execute(
                "INSERT OR IGNORE INTO events VALUES (?, ?, ?, 0, ?, ?)",
                (event_uuid, json.dumps(payload, separators=(",", ":")), snapshot_path, time.time(), time.time()),
            )
            db.commit()
            return cursor.rowcount == 1

    def ready(self, now: float | None = None) -> list[QueuedEvent]:
        timestamp = time.time() if now is None else now
        with closing(self._connect()) as db:
            rows = db.execute(
                "SELECT event_uuid, payload, snapshot_path, attempts FROM events "
                "WHERE next_attempt <= ? ORDER BY created_at, rowid",
                (timestamp,),
            ).fetchall()
        return [QueuedEvent(row[0], json.loads(row[1]), row[2], row[3]) for row in rows]

    def retry(self, event_uuid: str, attempts: int, now: float | None = None) -> None:
        delay = min(300.0, 2 ** min(attempts, 8)) + random.uniform(0, 1)
        with closing(self._connect()) as db:
            db.execute(
                "UPDATE events SET attempts = ?, next_attempt = ? WHERE event_uuid = ?",
                (attempts, (time.time() if now is None else now) + delay, event_uuid),
            )
            db.commit()

    def acknowledge(self, event_uuid: str) -> None:
        with closing(self._connect()) as db:
            row = db.execute("SELECT snapshot_path FROM events WHERE event_uuid = ?", (event_uuid,)).fetchone()
            db.execute("DELETE FROM events WHERE event_uuid = ?", (event_uuid,))
            db.commit()
        if row and row[0]:
            Path(row[0]).unlink(missing_ok=True)

    def _connect(self) -> sqlite3.Connection:
        return sqlite3.connect(self.path)
