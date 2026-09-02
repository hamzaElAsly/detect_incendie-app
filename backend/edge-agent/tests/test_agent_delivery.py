from pathlib import Path
import tempfile
import unittest
from unittest.mock import Mock
from types import SimpleNamespace
import numpy as np

from src.api_client import ApiError
from src.main import EdgeAgent
from src.camera_stream import FrameResult
from src.health_state import HealthState, StreamState
from src.inference_service import Detection
from src.snapshot_service import SnapshotService
from src.temporal_filter import TemporalDetectionFilter


class AgentDeliveryTests(unittest.TestCase):
    def agent(self):
        agent = EdgeAgent.__new__(EdgeAgent)
        agent.api = Mock(); agent.queue = Mock(); agent.settings = Mock(camera_id="camera-1")
        return agent

    def test_success_deletes_snapshot_only_after_acknowledgement(self):
        with tempfile.TemporaryDirectory() as directory:
            snapshot = Path(directory) / "snapshot.jpg"; snapshot.write_bytes(b"jpeg")
            agent = self.agent(); agent._deliver_or_queue("uuid", {"event_uuid": "uuid"}, snapshot)
            self.assertFalse(snapshot.exists()); agent.queue.enqueue.assert_not_called()

    def test_retryable_failure_preserves_snapshot_and_stable_uuid_in_queue(self):
        with tempfile.TemporaryDirectory() as directory:
            snapshot = Path(directory) / "snapshot.jpg"; snapshot.write_bytes(b"jpeg")
            agent = self.agent(); agent.api.send_detection.side_effect = ApiError("offline", retryable=True)
            payload = {"event_uuid": "stable-uuid"}
            agent._deliver_or_queue("stable-uuid", payload, snapshot)
            self.assertTrue(snapshot.exists())
            agent.queue.enqueue.assert_called_once_with("stable-uuid", payload, str(snapshot), "camera-1")

    def test_permanent_failure_does_not_fill_queue(self):
        with tempfile.TemporaryDirectory() as directory:
            snapshot = Path(directory) / "snapshot.jpg"; snapshot.write_bytes(b"jpeg")
            agent = self.agent(); agent.api.send_detection.side_effect = ApiError("invalid", 422, False)
            agent._deliver_or_queue("uuid", {"event_uuid": "uuid"}, snapshot)
            self.assertFalse(snapshot.exists()); agent.queue.enqueue.assert_not_called()

    def test_dry_run_pipeline_confirms_distinct_frames_and_cleans_snapshot(self):
        with tempfile.TemporaryDirectory() as directory:
            frame = np.zeros((48, 64, 3), dtype=np.uint8)
            health = HealthState(state=StreamState.ONLINE)
            stream = Mock(health=health, _resolution=(64, 48))
            stream.results.return_value = iter([FrameResult(frame, 1, 64, 48) for _ in range(3)])
            detection = Detection("fire", 0, .95, {"x": .1, "y": .2, "width": .3, "height": .4}, 1)
            detector = Mock(); detector.predict.return_value = [detection, detection]
            detector.loader.info = SimpleNamespace(name="mock-detector", version="mock", business_validated=False)
            agent = EdgeAgent.__new__(EdgeAgent)
            agent.settings = SimpleNamespace(agent_id="test", dry_run=True, mock_detector=True,
                inference_fps=float("inf"), minimum_positive_frames=3, camera_id="camera")
            agent.api = Mock(); agent.queue = Mock(); agent.snapshots = SnapshotService(Path(directory))
            agent.stream = stream; agent.detector = detector
            agent.temporal = TemporalDetectionFilter({"fire": .6, "smoke": .55}, 3, 3, 30, 10)
            agent.last_detection_at = None; agent.last_reconnect_count = 0
            agent.heartbeat = Mock(); agent.stopping = False; agent._stopped = False
            agent.run()
            self.assertIsNotNone(agent.last_detection_at)
            self.assertEqual(list(Path(directory).iterdir()), [])
            agent.api.close.assert_called_once()


if __name__ == "__main__": unittest.main()
