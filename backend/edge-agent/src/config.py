from __future__ import annotations

from dataclasses import dataclass
import os
from pathlib import Path

from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parents[1]
load_dotenv(ROOT / ".env")


def _positive_int(name: str, default: int) -> int:
    value = int(os.getenv(name, str(default)))
    if value <= 0:
        raise ValueError(f"{name} must be positive")
    return value


def _threshold(name: str, default: float) -> float:
    value = float(os.getenv(name, str(default)))
    if not 0 <= value <= 1:
        raise ValueError(f"{name} must be between 0 and 1")
    return value


@dataclass(frozen=True)
class EdgeConfig:
    api_base_url: str = os.getenv("EDGE_API_BASE_URL", "http://localhost/api").rstrip("/")
    api_client_token: str = os.getenv("EDGE_API_CLIENT_TOKEN", "")
    camera_id: str = os.getenv("EDGE_CAMERA_ID", "")
    model_path: Path = Path(os.getenv("EDGE_MODEL_PATH", "../Python/runs/fire_factory/small/weights/best.pt"))
    device: str = os.getenv("EDGE_DEVICE", "cpu")
    source: str = os.getenv("EDGE_SOURCE", "0")
    heartbeat_interval: int = _positive_int("EDGE_HEARTBEAT_INTERVAL", 30)
    inference_fps: float = float(os.getenv("EDGE_INFERENCE_FPS", "2"))
    fire_threshold: float = _threshold("EDGE_FIRE_THRESHOLD", .90)
    smoke_threshold: float = _threshold("EDGE_SMOKE_THRESHOLD", .85)
    minimum_positive_frames: int = _positive_int("EDGE_MIN_POSITIVE_FRAMES", 3)
    confirmation_window_seconds: float = float(os.getenv("EDGE_CONFIRMATION_WINDOW_SECONDS", "5"))
    cooldown_seconds: float = float(os.getenv("EDGE_EVENT_COOLDOWN_SECONDS", "30"))
    end_timeout_seconds: float = float(os.getenv("EDGE_EVENT_END_TIMEOUT_SECONDS", "10"))
    verify_tls: bool = os.getenv("EDGE_VERIFY_TLS", "true").lower() in {"1", "true", "yes"}

    def validate_runtime(self) -> None:
        if not self.api_client_token:
            raise ValueError("EDGE_API_CLIENT_TOKEN is required")
        if not self.camera_id:
            raise ValueError("EDGE_CAMERA_ID is required")
        if not self.model_path.is_file():
            raise ValueError(f"Model not found: {self.model_path}")
        if self.inference_fps <= 0:
            raise ValueError("EDGE_INFERENCE_FPS must be positive")
