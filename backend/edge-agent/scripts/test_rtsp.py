from __future__ import annotations

import argparse
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from src.camera_stream import CameraStream
from src.utils import redact_url


def main() -> int:
    parser = argparse.ArgumentParser(description="Test an RTSP/webcam/video source without exposing credentials")
    parser.add_argument("--source", required=True)
    parser.add_argument("--source-type", choices=["rtsp", "webcam", "file"], default="rtsp")
    parser.add_argument("--frames", type=int, default=20)
    args = parser.parse_args()
    stream = CameraStream(args.source, args.source_type)
    started = time.monotonic()
    try:
        stream.open()
        results = [stream.read() for _ in range(args.frames)]
    except Exception as exc:
        print(f"FAILED source={redact_url(args.source)} error={exc.__class__.__name__}", file=sys.stderr)
        return 1
    finally:
        stream.close()
    elapsed = time.monotonic() - started
    last = results[-1]
    print(f"OK source={redact_url(args.source)} frames={len(results)} resolution={last.width}x{last.height} observed_fps={len(results)/elapsed:.2f}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
