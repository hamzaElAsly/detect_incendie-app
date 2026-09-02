import os
import unittest
from unittest.mock import patch

from src.retry_policy import RetryPolicy
from src.settings import EdgeSettings
from src.utils import redact_url


class SettingsRetryUtilsTests(unittest.TestCase):
    def test_mock_dry_run_does_not_require_model_or_api(self):
        with patch.dict(os.environ, {"EDGE_AGENT_ID": "agent", "EDGE_SOURCE": "0", "EDGE_SOURCE_TYPE": "webcam", "EDGE_DRY_RUN": "true", "EDGE_MOCK_DETECTOR": "true"}, clear=True):
            settings = EdgeSettings(); settings.validate()
            self.assertFalse(settings.model_business_validated)

    def test_invalid_threshold_is_rejected(self):
        with patch.dict(os.environ, {"FIRE_CONF_THRESHOLD": "1.1"}, clear=True):
            with self.assertRaises(ValueError): EdgeSettings()

    def test_v2_model_path_version_and_threshold_env_are_supported(self):
        env = {
            "EDGE_AGENT_ID": "agent",
            "EDGE_SOURCE": "0",
            "EDGE_SOURCE_TYPE": "webcam",
            "YOLO_MODEL_PATH": "Python/models/fire_smoke_v2/best.pt",
            "YOLO_MODEL_VERSION": "v2",
            "FIRE_CONF_THRESHOLD": "0.30",
            "SMOKE_CONF_THRESHOLD": "0.31",
        }
        with patch.dict(os.environ, env, clear=True):
            settings = EdgeSettings()
            self.assertTrue(settings.model_path.is_file())
            self.assertEqual(settings.model_path.name, "best.pt")
            self.assertEqual(settings.model_version, "v2")
            self.assertEqual(settings.fire_threshold, .30)
            self.assertEqual(settings.smoke_threshold, .31)

    def test_url_credentials_are_redacted(self):
        value = redact_url("rtsp://user:password@camera.local:554/live")
        self.assertNotIn("user", value); self.assertNotIn("password", value)
        self.assertIn("camera.local", value)

    def test_retry_is_bounded_and_honors_retry_after(self):
        policy = RetryPolicy(5, 1, 10)
        self.assertEqual(policy.delay(1, "3"), 3)
        self.assertLessEqual(policy.delay(20), 10)

    def test_generic_model_requires_dry_run_and_never_business_validation(self):
        base = {"EDGE_AGENT_ID": "agent", "EDGE_SOURCE": "0", "EDGE_SOURCE_TYPE": "webcam",
                "EDGE_ALLOW_GENERIC_MODEL": "true", "EDGE_MOCK_DETECTOR": "true",
                "EDGE_DRY_RUN": "false", "EDGE_API_BASE_URL": "http://api.test/api",
                "EDGE_API_CLIENT_TOKEN": "token", "EDGE_CAMERA_ID": "camera"}
        with patch.dict(os.environ, base, clear=True):
            with self.assertRaises(ValueError): EdgeSettings().validate()
        base.update({"EDGE_DRY_RUN": "true", "EDGE_MODEL_BUSINESS_VALIDATED": "true"})
        with patch.dict(os.environ, base, clear=True):
            with self.assertRaises(ValueError): EdgeSettings().validate()


if __name__ == "__main__": unittest.main()
