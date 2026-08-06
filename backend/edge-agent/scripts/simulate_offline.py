from __future__ import annotations

import tempfile
from pathlib import Path
import sys
import uuid

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from src.offline_queue import OfflineQueue


def main() -> int:
    with tempfile.TemporaryDirectory(prefix="edge-offline-") as directory:
        root = Path(directory); database = root / "edge_queue.sqlite3"; snapshot = root / "snapshot.jpg"
        snapshot.write_bytes(b"diagnostic-snapshot")
        event_uuid = str(uuid.uuid4())
        queue = OfflineQueue(database)
        queue.enqueue(event_uuid, {"event_uuid": event_uuid}, str(snapshot), "diagnostic-camera")
        queue.mark_sending(event_uuid)  # Simulate process loss during upload.
        recovered = OfflineQueue(database)
        ready = recovered.peek_ready()
        if len(ready) != 1 or ready[0].status != "retry" or not snapshot.exists():
            print("FAILED crash recovery invariant", file=sys.stderr)
            return 1
        recovered.mark_sent(event_uuid)
        if recovered.count() or snapshot.exists():
            print("FAILED acknowledgement cleanup invariant", file=sys.stderr)
            return 1
        print("OK enqueue=1 recovered_status=retry acknowledged=1 snapshot_deleted=1")
        return 0


if __name__ == "__main__":
    raise SystemExit(main())
