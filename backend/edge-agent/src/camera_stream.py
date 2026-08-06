from __future__ import annotations

from dataclasses import dataclass
import hashlib
import logging
import os
import random
import threading
import time
from typing import Callable

import cv2

from .health_state import HealthState, StreamState
from .utils import redact_url

logger = logging.getLogger(__name__)


@dataclass(frozen=True)
class FrameResult:
    frame: object
    captured_at: float
    width: int
    height: int


class CameraStreamError(RuntimeError):
    pass


class EndOfStream(CameraStreamError):
    pass


class CameraStream:
    def __init__(self, source: str, source_type: str = "rtsp", connect_timeout: float = 10,
                 read_timeout: float = 10, reconnect_initial: float = 1, reconnect_max: float = 60,
                 width: int | None = None, height: int | None = None, stable_frames: int = 3,
                 frozen_frame_limit: int = 30):
        self.source_text = source
        self.source_type = source_type
        self.source = int(source) if source_type == "webcam" and source.isdigit() else source
        self.connect_timeout, self.read_timeout = connect_timeout, read_timeout
        self.reconnect_initial, self.reconnect_max = reconnect_initial, reconnect_max
        self.width, self.height, self.stable_frames = width, height, stable_frames
        self.frozen_frame_limit = frozen_frame_limit
        self.capture = None
        self.health = HealthState()
        self._lock = threading.Lock()
        self._reconnect_lock = threading.Lock()
        self._stop = threading.Event()
        self._last_digest: bytes | None = None
        self._same_frames = 0
        self._resolution: tuple[int, int] | None = None
        self._valid_since_open = 0
        self.on_state_change: Callable[[], None] | None = None

    def _transition(self, state: StreamState) -> None:
        if self.health.state == state:
            return
        self.health.state = state
        if self.on_state_change:
            self.on_state_change()

    def open(self) -> None:
        with self._lock:
            self.close()
            self._transition(StreamState.CONNECTING)
            if self.source_type == "rtsp":
                os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp")
            backend = cv2.CAP_FFMPEG if self.source_type in {"rtsp", "file"} else cv2.CAP_ANY
            params = [cv2.CAP_PROP_OPEN_TIMEOUT_MSEC, int(self.connect_timeout * 1000),
                      cv2.CAP_PROP_READ_TIMEOUT_MSEC, int(self.read_timeout * 1000)]
            try:
                capture = cv2.VideoCapture(self.source, backend, params)
            except (TypeError, cv2.error):
                # Some webcam backends reject FFmpeg-only open parameters.
                capture = cv2.VideoCapture(self.source, backend)
            if not capture.isOpened():
                capture.release()
                self._fail("open_failed", "Unable to open camera stream")
                raise CameraStreamError("Unable to open camera stream")
            self.capture = capture
            self._valid_since_open = 0
            self._last_digest = None
            self._same_frames = 0
            logger.info("stream_opened source=%s type=%s", redact_url(self.source_text), self.source_type)

    def read(self) -> FrameResult:
        if self.capture is None or not self.capture.isOpened():
            raise CameraStreamError("Stream is not open")
        started = time.monotonic()
        ok, frame = self.capture.read()
        elapsed = time.monotonic() - started
        if not ok or frame is None or getattr(frame, "size", 0) == 0:
            if self.source_type == "file":
                frame_count = self.capture.get(cv2.CAP_PROP_FRAME_COUNT)
                position = self.capture.get(cv2.CAP_PROP_POS_FRAMES)
                if frame_count > 0 and position >= frame_count:
                    raise EndOfStream("Video file reached end of stream")
            self._fail("empty_frame", "Camera returned an empty frame")
            raise CameraStreamError("Empty frame")
        if elapsed > self.read_timeout:
            self._fail("read_timeout", "Frame read timeout")
            raise CameraStreamError("Frame read timeout")
        height, width = frame.shape[:2]
        resolution = (width, height)
        if self._resolution and resolution != self._resolution:
            logger.warning("resolution_changed old=%sx%s new=%sx%s", *self._resolution, width, height)
            self._transition(StreamState.DEGRADED)
            self._valid_since_open = 0
        self._resolution = resolution
        processed = frame
        if self.width or self.height:
            target_width = self.width or max(1, round(width * (self.height / height)))
            target_height = self.height or max(1, round(height * (self.width / width)))
            processed = cv2.resize(frame, (target_width, target_height))
        sample = cv2.resize(processed, (32, 32)).tobytes()
        digest = hashlib.blake2s(sample, digest_size=8).digest()
        self._same_frames = self._same_frames + 1 if digest == self._last_digest else 0
        self._last_digest = digest
        if self._same_frames >= self.frozen_frame_limit:
            self._fail("frozen_stream", "Camera stream appears frozen")
            raise CameraStreamError("Frozen stream")
        captured_at = time.time()
        self.health.record_frame(captured_at)
        self._valid_since_open += 1
        self.health.error_code = self.health.error_message = None
        if self._valid_since_open >= self.stable_frames:
            self._transition(StreamState.ONLINE)
            self.health.mark_online()
        return FrameResult(processed, captured_at, width, height)

    def reconnect(self) -> None:
        if not self._reconnect_lock.acquire(blocking=False):
            raise CameraStreamError("Reconnection already in progress")
        try:
            delay = self.reconnect_initial
            while not self._stop.is_set():
                self._transition(StreamState.RECONNECTING)
                self.health.reconnects += 1
                self.close()
                if self._stop.wait(delay + random.uniform(0, min(1, delay * .2))):
                    break
                try:
                    self.open()
                    for _ in range(self.stable_frames):
                        self.read()
                    self._transition(StreamState.ONLINE)
                    return
                except (CameraStreamError, cv2.error):
                    delay = min(self.reconnect_max, delay * 2)
        finally:
            self._reconnect_lock.release()
        raise CameraStreamError("Stream stopped")

    def results(self):
        try:
            self.open()
            while not self._stop.is_set():
                try:
                    yield self.read()
                except EndOfStream:
                    logger.info("file_stream_ended source=%s", redact_url(self.source_text))
                    break
                except (CameraStreamError, cv2.error):
                    if self._stop.is_set():
                        break
                    try:
                        self.reconnect()
                    except CameraStreamError:
                        if self._stop.is_set():
                            break
                        raise
        finally:
            self.close()

    def frames(self):
        for result in self.results():
            yield result.frame

    def stop(self) -> None:
        self._stop.set()
        self.close()
        self._transition(StreamState.STOPPED)

    def close(self) -> None:
        if self.capture is not None:
            self.capture.release()
        self.capture = None

    def _fail(self, code: str, message: str) -> None:
        self._transition(StreamState.OFFLINE)
        self.health.mark_offline()
        self.health.error_code, self.health.error_message = code, message


RTSPStream = CameraStream
