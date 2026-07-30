from __future__ import annotations

import time

import cv2


class CameraStream:
    def __init__(self, source: str, reconnect_max_seconds: float = 30):
        self.source = int(source) if source.isdigit() else source
        self.reconnect_max = reconnect_max_seconds
        self.capture = None

    def frames(self):
        delay = 1.0
        while True:
            if self.capture is None or not self.capture.isOpened():
                self.capture = cv2.VideoCapture(self.source)
            ok, frame = self.capture.read()
            if ok:
                delay = 1.0
                yield frame
                continue
            self.close()
            time.sleep(delay)
            delay = min(self.reconnect_max, delay * 2)

    def close(self) -> None:
        if self.capture is not None:
            self.capture.release()
        self.capture = None
