import tempfile
import unittest
from datetime import datetime, timedelta
from typing import Optional
from pathlib import Path
from unittest.mock import MagicMock, patch

import numpy as np
from sqlalchemy import create_engine
from sqlalchemy.orm import Session

from database import Base
from repositories.fire_event_repository import FireEventRepository
from services.detection_gate import DetectionGate


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

class DetectionGate:
    def __init__( self,threshold: float,required_positive_frames: int,cooldown_seconds: int, ):
        self.threshold = threshold
        self.required_positive_frames = required_positive_frames
        self.cooldown_seconds = cooldown_seconds
        self.positive_frames = 0
        self.last_detection_at: Optional[datetime] = None

    def observe( self, is_fire: bool, confidence: float, now: Optional[datetime] = None, ) -> bool:
        now = now or datetime.now()
        # Une frame négative ou insuffisante remet la séquence à zéro
        if not is_fire or confidence < self.threshold:
            self.positive_frames = 0
            return False
        # Pendant le cooldown, ne pas accumuler les frames positives
        if self.last_detection_at is not None:
            elapsed = (now - self.last_detection_at).total_seconds()
            if elapsed < self.cooldown_seconds:
                self.positive_frames = 0
                return False
        self.positive_frames += 1
        if self.positive_frames < self.required_positive_frames:
            return False
        self.last_detection_at = now
        self.positive_frames = 0
        return True

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


class DatabaseConnectionTests(unittest.TestCase):
    def test_connection_function(self):
        import database
        connection = MagicMock(); context = MagicMock(); context.__enter__.return_value = connection
        engine = MagicMock(); engine.connect.return_value = context
        with patch.object(database, "get_engine", return_value=engine):
            connected, _ = database.test_connection()
        self.assertTrue(connected); connection.execute.assert_called_once()


if __name__ == "__main__": unittest.main()
