import logging
import threading
import time
import unittest
import uuid
from unittest.mock import Mock

from src.event_factory import create_event, EventFactoryError
from src.heartbeat_service import HeartbeatService
from src.inference_service import Detection, best_detections_by_class
from src.logging_config import SecretFilter
from src.model_loader import ModelLoader


class RuntimeServiceTests(unittest.TestCase):
    def test_same_class_boxes_count_as_one_observation_source(self):
        detections = [
            Detection("fire", 0, .7, {"x": 0., "y": 0., "width": .2, "height": .2}, 1),
            Detection("fire", 0, .9, {"x": .1, "y": .1, "width": .2, "height": .2}, 1),
            Detection("smoke", 1, .8, {"x": .2, "y": .2, "width": .2, "height": .2}, 1),
        ]
        selected = best_detections_by_class(detections)
        self.assertEqual(len(selected), 2)
        self.assertEqual(next(d for d in selected if d.class_name == "fire").confidence, .9)

    def test_event_factory_creates_stable_uuid4_contract_without_factory(self):
        detection = Detection("fire", 0, .94, {"x": .1, "y": .2, "width": .3, "height": .4}, 12.5)
        event = create_event(detection, 1920, 1080, "fire-smoke-yolo", "v2", 3)
        self.assertEqual(uuid.UUID(event.event_uuid).version, 4)
        self.assertEqual(event.payload["event_uuid"], event.event_uuid)
        self.assertEqual(event.payload["class_id"], 0)
        self.assertEqual(event.payload["model_version"], "v2")
        self.assertNotIn("factory_id", event.payload)
        self.assertTrue(event.payload["detected_at"].endswith("Z"))

    def test_event_factory_rejects_invalid_normalized_detection(self):
        invalid = Detection("flame", 9, 1.2, {"x": .9, "y": .2, "width": .2, "height": .2}, 1)
        with self.assertRaises(EventFactoryError):
            create_event(invalid, 1920, 1080, "fire-smoke-yolo", "v1", 3)

    def test_event_factory_rejects_class_id_mapping_mismatch(self):
        invalid = Detection("smoke", 0, .8, {"x": .1, "y": .2, "width": .3, "height": .4}, 1)
        with self.assertRaises(EventFactoryError):
            create_event(invalid, 1920, 1080, "fire-smoke-yolo", "v2", 3)

    def test_mock_model_is_never_business_validated(self):
        loader = ModelLoader(None, mock=True, business_validated=True)
        self.assertIsNone(loader.load())
        self.assertFalse(loader.info.business_validated)

    def test_state_change_wakes_heartbeat_loop(self):
        api = Mock(); sent = threading.Event()
        api.send_heartbeat.side_effect = lambda payload, **_: sent.set()
        heartbeat = HeartbeatService(api, 60, lambda: {"status": "online"})
        heartbeat.start(); self.assertTrue(sent.wait(1)); sent.clear()
        heartbeat.notify_state_change(); self.assertTrue(sent.wait(1))
        heartbeat.stop(final=False)
        self.assertGreaterEqual(api.send_heartbeat.call_count, 2)

    def test_periodic_and_final_heartbeat(self):
        api = Mock()
        heartbeat = HeartbeatService(api, .02, lambda: {"status": "online"})
        heartbeat.start(); time.sleep(.06); heartbeat.stop(final=True)
        self.assertGreaterEqual(api.send_heartbeat.call_count, 3)
        self.assertEqual(api.send_heartbeat.call_args.args[0]["status"], "error")

    def test_log_filter_redacts_tokens_and_rtsp_credentials(self):
        record = logging.LogRecord("test", logging.INFO, __file__, 1,
            "token=abc Authorization: Bearer super-secret rtsp://user:pass@camera/live", (), None)
        SecretFilter().filter(record)
        message = record.getMessage()
        self.assertNotIn("abc", message); self.assertNotIn("super-secret", message); self.assertNotIn("user:pass", message)


if __name__ == "__main__": unittest.main()
