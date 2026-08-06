from pathlib import Path
from types import SimpleNamespace
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

from src.inference_service import InferenceService
from src.model_loader import ModelLoader


class FakeModel:
    def __init__(self, names):
        self.names = names
        self.predict = Mock(return_value=[SimpleNamespace(boxes=[], names=names)])


class ModelLoaderTests(unittest.TestCase):
    def weight(self, directory):
        path = Path(directory) / "best.pt"; path.write_bytes(b"weights"); return path

    def test_loads_once_validates_exact_classes_and_warms_up(self):
        with tempfile.TemporaryDirectory() as directory:
            model = FakeModel({0: "fire", 1: "smoke"}); yolo = Mock(return_value=model)
            with patch.dict(sys.modules, {"ultralytics": SimpleNamespace(YOLO=yolo)}):
                loader = ModelLoader(self.weight(directory))
                self.assertIs(loader.load(), loader.load()); loader.warm_up(32)
            yolo.assert_called_once(); model.predict.assert_called_once()

    def test_unexpected_classes_are_rejected_by_default(self):
        with tempfile.TemporaryDirectory() as directory:
            model = FakeModel({0: "person"})
            with patch.dict(sys.modules, {"ultralytics": SimpleNamespace(YOLO=lambda _: model)}):
                with self.assertRaises(ValueError): ModelLoader(self.weight(directory)).load()

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
