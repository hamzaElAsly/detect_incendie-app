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

    def test_prediction_with_existing_image(self):
        from PIL import Image
        from services.fire_detection_service import get_fire_detection_service
        image = Image.open(Path(__file__).resolve().parents[1] / "test_feu.jpg")
        result = get_fire_detection_service().predict_pil(image)
        self.assertIn(result["label"], {"fire", "no_fire"})
        self.assertGreaterEqual(result["confidence"], 0.5)


class PredictionPipelineTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        from services.fire_detection_service import get_fire_detection_service
        cls.service = get_fire_detection_service()
        cls.root = Path(__file__).resolve().parents[1]

    def test_real_imagefolder_class_order_matches_metadata(self):
        from torchvision.datasets import ImageFolder
        dataset = ImageFolder(self.root / "data_set")
        metadata = load_model_metadata()
        self.assertEqual(dataset.classes, metadata["dataset_classes"])
        self.assertEqual(dataset.class_to_idx, {"Fire": 0, "No_Fire": 1})
        self.assertEqual(metadata["classes"], ["fire", "no_fire"])

    def test_checkpoint_loaded_strictly_with_two_outputs(self):
        self.assertEqual(self.service.model.classifier.out_features, 2)
        self.assertEqual(self.service.model.classifier.in_features, 64)
        self.assertFalse(self.service.model.training)

    def test_preprocessing_shape_and_finite_values(self):
        from PIL import Image
        import torch
        tensor = self.service.transform(Image.new("RGB", (640, 480), (10, 20, 30)))
        self.assertEqual(tuple(tensor.shape), (3, 224, 224))
        self.assertTrue(torch.isfinite(tensor).all())

    def test_opencv_bgr_to_rgb(self):
        import cv2
        rgb = np.array([[[255, 10, 20], [1, 200, 30]]], dtype=np.uint8)
        bgr = cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR)
        np.testing.assert_array_equal(np.asarray(opencv_frame_to_pil(bgr)), rgb)

    def test_pil_and_webcam_paths_are_identical(self):
        import cv2
        from PIL import Image
        image = Image.open(self.root / "Safe_Fire (3).png").convert("RGB")
        frame = cv2.cvtColor(np.asarray(image), cv2.COLOR_RGB2BGR)
        self.assertEqual(self.service.predict_pil(image), self.service.predict_frame(frame))

    def test_softmax_probabilities_and_mapping(self):
        from PIL import Image
        result = self.service.predict_pil(Image.open(self.root / "Safe_Fire (3).png"))
        self.assertAlmostEqual(sum(result["probabilities"].values()), 1.0, places=6)
        self.assertEqual(result["label"], self.service.classes[result["predicted_index"]])
        self.assertEqual(result["is_fire"], result["label"] == "fire")

    def test_known_normal_and_fire_examples(self):
        from PIL import Image
        normal = self.service.predict_pil(Image.open(self.root / "Safe_Fire (3).png"))
        fire = self.service.predict_pil(Image.open(self.root / "test_feu.jpg"))
        self.assertEqual(normal["label"], "no_fire")
        self.assertEqual(fire["label"], "fire")

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


class DatabaseConnectionTests(unittest.TestCase):
    def test_connection_function(self):
        import database
        connection = MagicMock(); context = MagicMock(); context.__enter__.return_value = connection
        engine = MagicMock(); engine.connect.return_value = context
        with patch.object(database, "get_engine", return_value=engine):
            connected, _ = database.test_connection()
        self.assertTrue(connected); connection.execute.assert_called_once()


if __name__ == "__main__": unittest.main()
