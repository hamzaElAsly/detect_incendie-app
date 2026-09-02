from pathlib import Path
from types import SimpleNamespace
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

from src.inference_service import InferenceService
from src.model_loader import ModelLoader


class FakeModel:
    def __init__(self, names, boxes=None):
        self.names = names
        self.predict = Mock(return_value=[SimpleNamespace(boxes=boxes or [], names=names)])


class FakeTensorValue:
    def __init__(self, value):
        self.value = value

    def item(self):
        return self.value


class FakeBox:
    def __init__(self, class_id, confidence, xyxy):
        self.cls = FakeTensorValue(class_id)
        self.conf = FakeTensorValue(confidence)
        self.xyxy = [SimpleNamespace(tolist=lambda: xyxy)]


class ModelLoaderTests(unittest.TestCase):
    def weight(self, directory):
        path = Path(directory) / "best.pt"; path.write_bytes(b"weights"); return path

    def test_loads_once_validates_exact_classes_and_warms_up(self):
        with tempfile.TemporaryDirectory() as directory:
            model = FakeModel({0: "fire", 1: "smoke"}); yolo = Mock(return_value=model)
            with patch.dict(sys.modules, {"ultralytics": SimpleNamespace(YOLO=yolo)}):
                loader = ModelLoader(self.weight(directory), model_version="v2")
                self.assertIs(loader.load(), loader.load()); loader.warm_up(32)
            yolo.assert_called_once(); model.predict.assert_called_once()
            self.assertEqual(loader.info.version, "v2")

    def test_unexpected_classes_are_rejected_by_default(self):
        with tempfile.TemporaryDirectory() as directory:
            model = FakeModel({0: "person"})
            with patch.dict(sys.modules, {"ultralytics": SimpleNamespace(YOLO=lambda _: model)}):
                with self.assertRaisesRegex(ValueError, "classes incompatible"): ModelLoader(self.weight(directory)).load()

    def test_missing_model_path_fails_explicitly(self):
        with tempfile.TemporaryDirectory() as directory:
            missing = Path(directory) / "missing.pt"
            with self.assertRaisesRegex(ValueError, "YOLO model file not found"):
                ModelLoader(missing).load()

    def test_inference_preserves_class_mapping_confidence_and_bbox(self):
        with tempfile.TemporaryDirectory() as directory:
            box = FakeBox(1, .42, [10, 20, 50, 80])
            model = FakeModel({0: "fire", 1: "smoke"}, [box])
            with patch.dict(sys.modules, {"ultralytics": SimpleNamespace(YOLO=lambda _: model)}):
                service = InferenceService(self.weight(directory), confidence_floor=.30)
            detections = service.predict(SimpleNamespace(shape=(100, 200, 3)))
            self.assertEqual(len(detections), 1)
            self.assertEqual(detections[0].class_name, "smoke")
            self.assertEqual(detections[0].class_id, 1)
            self.assertAlmostEqual(detections[0].confidence, .42)
            self.assertEqual(detections[0].bounding_box, {"x": .05, "y": .2, "width": .2, "height": .6})
            self.assertEqual(model.predict.call_args.kwargs["conf"], .30)

    def test_generic_weights_are_technical_only(self):
        with tempfile.TemporaryDirectory() as directory:
            model = FakeModel({0: "person"})
            with patch.dict(sys.modules, {"ultralytics": SimpleNamespace(YOLO=lambda _: model)}):
                loader = ModelLoader(self.weight(directory), allow_generic=True, business_validated=True)
                loader.load()
            self.assertEqual(loader.info.name, "generic-yolo")
            self.assertFalse(loader.info.business_validated)

    def test_cuda_unavailable_falls_back_to_cpu(self):
        with tempfile.TemporaryDirectory() as directory:
            model = FakeModel({0: "fire", 1: "smoke"})
            modules = {"torch": SimpleNamespace(cuda=SimpleNamespace(is_available=lambda: False)),
                       "ultralytics": SimpleNamespace(YOLO=lambda _: model)}
            with patch.dict(sys.modules, modules):
                loader = ModelLoader(self.weight(directory), device="cuda:0"); loader.load()
            self.assertEqual(loader.device, "cpu")

    def test_cuda_runtime_error_retries_inference_on_cpu(self):
        model = FakeModel({0: "fire", 1: "smoke"})
        model.predict.side_effect = [RuntimeError("CUDA out of memory"), [SimpleNamespace(boxes=[], names=model.names)]]
        loader = Mock(device="cuda:0"); loader.load.return_value = model
        service = InferenceService(loader=loader)
        self.assertEqual(service.predict(SimpleNamespace(shape=(10, 10, 3))), [])
        self.assertEqual(loader.device, "cpu")
        self.assertEqual(model.predict.call_args_list[-1].kwargs["device"], "cpu")


if __name__ == "__main__": unittest.main()
