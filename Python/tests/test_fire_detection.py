import tempfile
import unittest
from datetime import datetime, timedelta
from pathlib import Path
from unittest.mock import MagicMock, patch

import numpy as np
from sqlalchemy import create_engine
from sqlalchemy.orm import Session

from database import Base
from repositories.fire_event_repository import FireEventRepository
from services.detection_gate import DetectionGate
from services.image_preprocessing import load_model_metadata, opencv_frame_to_pil


class RepositoryTests(unittest.TestCase):
    def setUp(self):
        self.engine = create_engine("sqlite+pysqlite:///:memory:")
        Base.metadata.create_all(self.engine)
        self.session = Session(self.engine)
        self.repo = FireEventRepository(self.session)

    def tearDown(self):
        self.session.close(); self.engine.dispose()

    def test_create_read_and_updates(self):
        event = self.repo.create("storage/detections/test.jpg", 0.91, "webcam")
        self.assertEqual(self.repo.get(event.id).status, "detected")
        self.assertEqual(len(self.repo.list(source="webcam")), 1)
        self.assertEqual(self.repo.update_status(event.id, "confirmed").status, "confirmed")
        self.assertTrue(self.repo.update_alert(event.id, True).alert_sent)


class DetectionGateTests(unittest.TestCase):
    def test_positive_frames_and_cooldown(self):
        gate = DetectionGate(0.8, 3, 15)
        now = datetime(2026, 7, 12, 20, 30)
        self.assertFalse(gate.observe(True, 0.9, now))
        self.assertFalse(gate.observe(True, 0.9, now))
        self.assertTrue(gate.observe(True, 0.9, now))
        for _ in range(3): self.assertFalse(gate.observe(True, 0.9, now + timedelta(seconds=5)))
        gate.observe(True, 0.9, now + timedelta(seconds=16))
        gate.observe(True, 0.9, now + timedelta(seconds=16))
        self.assertTrue(gate.observe(True, 0.9, now + timedelta(seconds=16)))

    def test_negative_resets_sequence(self):
        gate = DetectionGate(0.8, 2, 0)
        self.assertFalse(gate.observe(True, 0.9))
        self.assertFalse(gate.observe(False, 0.99))
        self.assertFalse(gate.observe(True, 0.9))

class CaptureAndModelTests(unittest.TestCase):
    def test_save_capture(self):
        import services.capture_service as captures
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory); target = root / "storage" / "detections"
            with patch.object(captures, "PROJECT_ROOT", root), patch.object(captures, "DETECTIONS_DIR", target):
                relative = captures.save_capture(np.zeros((20, 20, 3), dtype=np.uint8), datetime(2026, 7, 12))
                self.assertTrue((root / relative).is_file())
                self.assertTrue(relative.startswith("storage/detections/fire_20260712_"))

class PredictionPipelineTests(unittest.TestCase):
    def setUp(self):
        from services.fire_detection_service import FireDetectionService
        self.root = Path(__file__).resolve().parents[1]
        self.boxes = MagicMock()
        self.boxes.cls = [MagicMock(item=lambda: 1)]
        self.boxes.conf = [MagicMock(item=lambda: 0.753)]
        self.boxes.xyxy = [MagicMock(tolist=lambda: [10.0, 20.0, 110.0, 220.0])]
        self.model = MagicMock(names={0: "fire", 1: "smoke"})
        self.model.predict.return_value = [MagicMock(boxes=self.boxes)]
        self.yolo = patch("services.fire_detection_service.YOLO", return_value=self.model)
        self.yolo.start()
        self.addCleanup(self.yolo.stop)
        self.service = FireDetectionService(self.root / "models" / "fire_smoke_v2" / "best.pt")

    def test_model_path_resolution_and_single_load(self):
        self.assertEqual(self.service.model_path, (self.root / "models" / "fire_smoke_v2" / "best.pt").resolve())
        self.model.predict.assert_not_called()

    def test_missing_model_path(self):
        from services.fire_detection_service import FireDetectionService
        with self.assertRaises(FileNotFoundError):
            FireDetectionService(self.root / "missing-best.pt")

    def test_correct_and_invalid_class_mapping(self):
        from services.fire_detection_service import FireDetectionService
        self.assertEqual(self.service.classes, {0: "fire", 1: "smoke"})
        self.model.names = {0: "smoke", 1: "fire"}
        with self.assertRaisesRegex(ValueError, "Mapping du modèle invalide"):
            FireDetectionService(self.root / "models" / "fire_smoke_v2" / "best.pt")

    def test_smoke_detection_and_bounding_box_serialization(self):
        result = self.service.predict_pil(MagicMock(convert=lambda _: MagicMock()))
        self.assertTrue(result["has_smoke"])
        self.assertFalse(result["has_fire"])
        self.assertEqual(result["detections"][0]["class_name"], "smoke")
        self.assertEqual(result["detections"][0]["bbox"], {"x1": 10.0, "y1": 20.0, "x2": 110.0, "y2": 220.0})
        self.model.predict.assert_called_once()

    def test_fire_detection_and_no_detections(self):
        self.boxes.cls = [MagicMock(item=lambda: 0)]
        result = self.service.predict_pil(MagicMock(convert=lambda _: MagicMock()))
        self.assertTrue(result["has_fire"])
        self.model.predict.return_value = [MagicMock(boxes=None)]
        result = self.service.predict_pil(MagicMock(convert=lambda _: MagicMock()))
        self.assertFalse(result["has_detection"])
        self.assertEqual(result["max_confidence"], 0.0)

    def test_confidence_filtering(self):
        self.boxes.conf = [MagicMock(item=lambda: 0.29)]
        result = self.service.predict_pil(MagicMock(convert=lambda _: MagicMock()))
        self.assertEqual(result["detections"], [])

    def test_opencv_bgr_to_rgb(self):
        import cv2
        rgb = np.array([[[255, 10, 20], [1, 200, 30]]], dtype=np.uint8)
        bgr = cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR)
        from services.image_preprocessing import opencv_frame_to_pil
        np.testing.assert_array_equal(np.asarray(opencv_frame_to_pil(bgr)), rgb)

    def test_model_singleton(self):
        from services.fire_detection_service import get_fire_detection_service
        self.assertIs(get_fire_detection_service(), get_fire_detection_service())

    def test_interfaces_reference_central_service(self):
        for filename in ("app.py", "api.py"):
            source = (self.root / filename).read_text(encoding="utf-8")
            self.assertIn("get_fire_detection_service", source)
            self.assertNotIn("CNN_Bottleneck(", source)


class EventDecisionTests(unittest.TestCase):
    def setUp(self):
        self.engine = create_engine("sqlite+pysqlite:///:memory:")
        Base.metadata.create_all(self.engine)
        self.session = Session(self.engine)
        self.repo = FireEventRepository(self.session)

    def tearDown(self):
        self.session.close(); self.engine.dispose()

    def test_normal_does_not_create_event(self):
        gate = DetectionGate(0.95, 3, 15)
        for _ in range(5): self.assertFalse(gate.observe(False, 0.999))
        self.assertEqual(self.repo.list(), [])

    def test_confirmed_fire_can_create_event(self):
        gate = DetectionGate(0.95, 3, 15)
        self.assertFalse(gate.observe(True, 0.99)); self.assertFalse(gate.observe(True, 0.99))
        if gate.observe(True, 0.99):
            self.repo.create("storage/detections/confirmed.jpg", 0.99, "webcam")
        self.assertEqual(len(self.repo.list()), 1)


class PredictionApiValidationTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        import api
        cls.predict = staticmethod(api.predict)

    def assert_bad_request(self, content_type, content):
        import asyncio
        from fastapi import HTTPException
        upload = MagicMock(content_type=content_type)
        upload.read = MagicMock(return_value=content)
        async def read():
            return content
        upload.read = read
        with self.assertRaises(HTTPException) as context:
            asyncio.run(self.predict(upload))
        self.assertEqual(context.exception.status_code, 400)

    def test_invalid_mime_type(self):
        self.assert_bad_request("text/plain", b"not-image")

    def test_corrupted_image(self):
        self.assert_bad_request("image/png", b"not-image")

    def test_empty_image(self):
        self.assert_bad_request("image/png", b"")


class DatabaseConnectionTests(unittest.TestCase):
    def test_connection_function(self):
        import database
        connection = MagicMock(); context = MagicMock(); context.__enter__.return_value = connection
        engine = MagicMock(); engine.connect.return_value = context
        with patch.object(database, "get_engine", return_value=engine):
            connected, _ = database.test_connection()
        self.assertTrue(connected); connection.execute.assert_called_once()


if __name__ == "__main__": unittest.main()
