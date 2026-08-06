from __future__ import annotations

from dataclasses import dataclass, field
import os
from pathlib import Path
from urllib.parse import urlparse
import re
import math

from dotenv import load_dotenv

ROOT = Path(__file__).resolve().parents[1]
load_dotenv(ROOT / ".env")


def _text(name: str, default: str = "") -> str:
    return os.getenv(name, default).strip()


def _bool(name: str, default: bool) -> bool:
    value = _text(name, str(default)).lower()
    if value not in {"1", "0", "true", "false", "yes", "no", "on", "off"}:
        raise ValueError(f"{name} must be a boolean")
    return value in {"1", "true", "yes", "on"}


def _int(name: str, default: int, minimum: int = 1) -> int:
    value = int(_text(name, str(default)))
    if value < minimum:
        raise ValueError(f"{name} must be >= {minimum}")
    return value


def _float(name: str, default: float, minimum: float = 0.0) -> float:
    value = float(_text(name, str(default)))
    if not math.isfinite(value) or value < minimum:
        raise ValueError(f"{name} must be >= {minimum}")
    return value


def _threshold(name: str, default: float) -> float:
    value = _float(name, default)
    if value > 1:
        raise ValueError(f"{name} must be between 0 and 1")
    return value


def _path(name: str, default: str) -> Path:
    value = Path(_text(name, default))
    return value if value.is_absolute() else ROOT / value


@dataclass(frozen=True)
class EdgeSettings:
    agent_id: str = field(default_factory=lambda: _text("EDGE_AGENT_ID"))
    api_base_url: str = field(default_factory=lambda: _text("EDGE_API_BASE_URL").rstrip("/"))
    api_client_token: str = field(default_factory=lambda: _text("EDGE_API_CLIENT_TOKEN"))
    camera_id: str = field(default_factory=lambda: _text("EDGE_CAMERA_ID"))
    source: str = field(default_factory=lambda: _text("EDGE_SOURCE"))
    source_type: str = field(default_factory=lambda: _text("EDGE_SOURCE_TYPE", "rtsp").lower())
    model_path: Path = field(default_factory=lambda: _path("EDGE_MODEL_PATH", "models/best.pt"))
    device: str = field(default_factory=lambda: _text("EDGE_DEVICE", "cpu").lower())
    verify_tls: bool = field(default_factory=lambda: _bool("EDGE_VERIFY_TLS", True))
    heartbeat_interval_seconds: int = field(default_factory=lambda: _int("EDGE_HEARTBEAT_INTERVAL_SECONDS", 30))
    heartbeat_timeout_seconds: int = field(default_factory=lambda: _int("EDGE_HEARTBEAT_TIMEOUT_SECONDS", 10))
    inference_fps: float = field(default_factory=lambda: _float("EDGE_INFERENCE_FPS", 5, .01))
    frame_width: int | None = field(default_factory=lambda: int(_text("EDGE_FRAME_WIDTH")) if _text("EDGE_FRAME_WIDTH") else None)
    frame_height: int | None = field(default_factory=lambda: int(_text("EDGE_FRAME_HEIGHT")) if _text("EDGE_FRAME_HEIGHT") else None)
    model_image_size: int = field(default_factory=lambda: _int("EDGE_MODEL_IMAGE_SIZE", 640))
    fire_threshold: float = field(default_factory=lambda: _threshold("EDGE_FIRE_THRESHOLD", .60))
    smoke_threshold: float = field(default_factory=lambda: _threshold("EDGE_SMOKE_THRESHOLD", .55))
    minimum_positive_frames: int = field(default_factory=lambda: _int("EDGE_MIN_POSITIVE_FRAMES", 3))
    confirmation_window_seconds: float = field(default_factory=lambda: _float("EDGE_CONFIRMATION_WINDOW_SECONDS", 3, .01))
    cooldown_seconds: float = field(default_factory=lambda: _float("EDGE_EVENT_COOLDOWN_SECONDS", 30))
    end_timeout_seconds: float = field(default_factory=lambda: _float("EDGE_EVENT_END_TIMEOUT_SECONDS", 10, .01))
    rtsp_connect_timeout_seconds: float = field(default_factory=lambda: _float("EDGE_RTSP_CONNECT_TIMEOUT_SECONDS", 10, .01))
    rtsp_read_timeout_seconds: float = field(default_factory=lambda: _float("EDGE_RTSP_READ_TIMEOUT_SECONDS", 10, .01))
    reconnect_initial_seconds: float = field(default_factory=lambda: _float("EDGE_RTSP_RECONNECT_INITIAL_SECONDS", 1, .01))
    reconnect_max_seconds: float = field(default_factory=lambda: _float("EDGE_RTSP_RECONNECT_MAX_SECONDS", 60, .01))
    api_connect_timeout_seconds: float = field(default_factory=lambda: _float("EDGE_API_CONNECT_TIMEOUT_SECONDS", 5, .01))
    api_read_timeout_seconds: float = field(default_factory=lambda: _float("EDGE_API_READ_TIMEOUT_SECONDS", 15, .01))
    api_max_retries: int = field(default_factory=lambda: _int("EDGE_API_MAX_RETRIES", 5, 0))
    api_retry_initial_seconds: float = field(default_factory=lambda: _float("EDGE_API_RETRY_INITIAL_SECONDS", 1, .01))
    api_retry_max_seconds: float = field(default_factory=lambda: _float("EDGE_API_RETRY_MAX_SECONDS", 60, .01))
    queue_db_path: Path = field(default_factory=lambda: _path("EDGE_QUEUE_DB_PATH", "storage/queue/edge_queue.sqlite3"))
    queue_max_events: int = field(default_factory=lambda: _int("EDGE_QUEUE_MAX_EVENTS", 10_000))
    queue_max_bytes: int = field(default_factory=lambda: _int("EDGE_QUEUE_MAX_BYTES", 1_073_741_824))
    snapshot_dir: Path = field(default_factory=lambda: _path("EDGE_SNAPSHOT_DIR", "storage/snapshots"))
    snapshot_format: str = field(default_factory=lambda: _text("EDGE_SNAPSHOT_FORMAT", "jpg").lower())
    snapshot_quality: int = field(default_factory=lambda: _int("EDGE_SNAPSHOT_QUALITY", 85))
    snapshot_max_width: int = field(default_factory=lambda: _int("EDGE_SNAPSHOT_MAX_WIDTH", 1920))
    snapshot_retention_days: int = field(default_factory=lambda: _int("EDGE_SNAPSHOT_RETENTION_DAYS", 7))
    log_level: str = field(default_factory=lambda: _text("EDGE_LOG_LEVEL", "INFO").upper())
    log_dir: Path = field(default_factory=lambda: _path("EDGE_LOG_DIR", "storage/logs"))
    log_max_bytes: int = field(default_factory=lambda: _int("EDGE_LOG_MAX_BYTES", 10_485_760))
    log_backup_count: int = field(default_factory=lambda: _int("EDGE_LOG_BACKUP_COUNT", 5, 0))
    dry_run: bool = field(default_factory=lambda: _bool("EDGE_DRY_RUN", True))
    mock_detector: bool = field(default_factory=lambda: _bool("EDGE_MOCK_DETECTOR", False))
    model_warmup: bool = field(default_factory=lambda: _bool("EDGE_MODEL_WARMUP", False))
    model_business_validated: bool = field(default_factory=lambda: _bool("EDGE_MODEL_BUSINESS_VALIDATED", False))
    allow_generic_model: bool = field(default_factory=lambda: _bool("EDGE_ALLOW_GENERIC_MODEL", False))

    def validate(self) -> None:
        if self.source_type not in {"rtsp", "webcam", "file"}:
            raise ValueError("EDGE_SOURCE_TYPE must be rtsp, webcam, or file")
        if not self.agent_id or not self.source:
            raise ValueError("EDGE_AGENT_ID and EDGE_SOURCE are required")
        if self.source_type == "file" and not Path(self.source).is_file():
            raise ValueError("EDGE_SOURCE video file does not exist")
        if self.source_type == "rtsp" and urlparse(self.source).scheme not in {"rtsp", "rtsps"}:
            raise ValueError("EDGE_SOURCE must be an RTSP URL")
        if self.source_type == "webcam" and not self.source.isdigit():
            raise ValueError("EDGE_SOURCE must be a numeric webcam index")
        if not re.fullmatch(r"cpu|mps|cuda(?::\d+)?", self.device):
            raise ValueError("EDGE_DEVICE must be cpu, mps, cuda, or cuda:<index>")
        if self.snapshot_format not in {"jpg", "jpeg", "png", "webp"} or self.snapshot_quality > 100:
            raise ValueError("Invalid snapshot format or quality")
        if self.frame_width is not None and not 1 <= self.frame_width <= 16384:
            raise ValueError("EDGE_FRAME_WIDTH must be between 1 and 16384")
        if self.frame_height is not None and not 1 <= self.frame_height <= 16384:
            raise ValueError("EDGE_FRAME_HEIGHT must be between 1 and 16384")
        if self.log_level not in {"DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"}:
            raise ValueError("EDGE_LOG_LEVEL is invalid")
        if self.reconnect_initial_seconds > self.reconnect_max_seconds:
            raise ValueError("Reconnect initial delay cannot exceed maximum")
        if not self.mock_detector and not self.model_path.is_file():
            raise ValueError(f"Model not found: {self.model_path}")
        if not self.mock_detector and self.model_path.suffix.lower() != ".pt":
            raise ValueError("EDGE_MODEL_PATH must reference a .pt file")
        if self.model_business_validated and (self.mock_detector or self.model_path.name != "best.pt"):
            raise ValueError("Business validation requires a specialized best.pt and cannot be used with mock mode")
        if self.allow_generic_model and not self.dry_run:
            raise ValueError("Generic weights are permitted only in dry-run technical tests")
        if self.allow_generic_model and self.model_business_validated:
            raise ValueError("Generic weights cannot be business validated")
        if not self.dry_run and (not self.api_base_url or not self.api_client_token or not self.camera_id):
            raise ValueError("API URL, token, and camera ID are required outside dry-run")
        if self.api_base_url and urlparse(self.api_base_url).scheme not in {"http", "https"}:
            raise ValueError("EDGE_API_BASE_URL must use HTTP or HTTPS")

EdgeConfig = EdgeSettings
