import unittest
from unittest.mock import Mock, patch

import numpy as np

from src.camera_stream import CameraStream, CameraStreamError, EndOfStream
from src.health_state import StreamState


class CameraStreamTests(unittest.TestCase):
    @patch("src.camera_stream.cv2.VideoCapture")
    def test_open_read_close_and_resolution(self, video_capture):
        capture = Mock(); capture.isOpened.return_value = True
        capture.read.return_value = (True, np.zeros((48, 64, 3), dtype=np.uint8))
        video_capture.return_value = capture
        stream = CameraStream("0", "webcam", stable_frames=1)
        stream.open(); result = stream.read(); stream.close()
        self.assertEqual((result.width, result.height), (64, 48))
        self.assertEqual(stream.health.state, StreamState.ONLINE)
        capture.release.assert_called()

    @patch("src.camera_stream.cv2.VideoCapture")
    def test_open_failure_is_offline(self, video_capture):
        capture = Mock(); capture.isOpened.return_value = False; video_capture.return_value = capture
        stream = CameraStream("rtsp://user:pass@camera/live", "rtsp")
        with self.assertRaises(CameraStreamError): stream.open()
        self.assertEqual(stream.health.state, StreamState.OFFLINE)

    @patch("src.camera_stream.cv2.VideoCapture")
    def test_empty_and_frozen_frames_are_detected(self, video_capture):
        capture = Mock(); capture.isOpened.return_value = True; video_capture.return_value = capture
        stream = CameraStream("0", "webcam", frozen_frame_limit=1)
        stream.open(); capture.read.return_value = (False, None)
        with self.assertRaises(CameraStreamError): stream.read()
        capture.read.return_value = (True, np.zeros((20, 20, 3), dtype=np.uint8))
        stream.read()
        with self.assertRaises(CameraStreamError): stream.read()

    @patch("src.camera_stream.cv2.VideoCapture")
    def test_software_resize_preserves_source_dimensions(self, video_capture):
        capture = Mock(); capture.isOpened.return_value = True
        capture.read.return_value = (True, np.zeros((50, 100, 3), dtype=np.uint8)); video_capture.return_value = capture
        stream = CameraStream("0", "webcam", width=40)
        stream.open(); result = stream.read()
        self.assertEqual((result.width, result.height), (100, 50))
        self.assertEqual(result.frame.shape[:2], (20, 40))

    @patch("src.camera_stream.cv2.VideoCapture")
    def test_video_file_eof_is_not_reconnected(self, video_capture):
        capture = Mock(); capture.isOpened.return_value = True
        capture.read.return_value = (False, None)
        capture.get.side_effect = lambda prop: 10 if prop == 7 else 10
        video_capture.return_value = capture
        stream = CameraStream("video.mp4", "file")
        stream.open()
        with self.assertRaises(EndOfStream): stream.read()

    @patch("src.camera_stream.random.uniform", return_value=0)
    @patch("src.camera_stream.cv2.VideoCapture")
    def test_interruption_reconnects_and_verifies_stable_frames(self, video_capture, _random):
        first = Mock(); first.isOpened.return_value = True; first.read.return_value = (False, None)
        second = Mock(); second.isOpened.return_value = True
        second.read.return_value = (True, np.ones((20, 30, 3), dtype=np.uint8))
        video_capture.side_effect = [first, second]
        stream = CameraStream("rtsp://camera/live", "rtsp", reconnect_initial=.001, stable_frames=2,
                              frozen_frame_limit=100)
        results = stream.results()
        frame = next(results)
        self.assertEqual(frame.width, 30)
        self.assertEqual(stream.health.reconnects, 1)
        self.assertEqual(stream.health.state, StreamState.ONLINE)
        first.release.assert_called()
        stream.stop(); results.close()


if __name__ == "__main__": unittest.main()
