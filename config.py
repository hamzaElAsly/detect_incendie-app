"""Configuration applicative chargée depuis l'environnement ou le fichier .env."""
from dataclasses import dataclass
import os
from pathlib import Path

from dotenv import load_dotenv
from sqlalchemy.engine import URL

PROJECT_ROOT = Path(__file__).resolve().parent
load_dotenv(PROJECT_ROOT / ".env")


class ConfigurationError(RuntimeError):
    """Configuration absente ou invalide."""


def _float(name: str, default: str) -> float:
    try:
        return float(os.getenv(name, default))
    except ValueError as exc:
        raise ConfigurationError(f"{name} doit être un nombre.") from exc


def _int(name: str, default: str, minimum: int = 1) -> int:
    try:
        value = int(os.getenv(name, default))
    except ValueError as exc:
        raise ConfigurationError(f"{name} doit être un entier.") from exc
    if value < minimum:
        raise ConfigurationError(f"{name} doit être supérieur ou égal à {minimum}.")
    return value


def _bool(name: str, default: str = "false") -> bool:
    value = os.getenv(name, default).strip().lower()
    if value not in {"true", "false", "1", "0", "yes", "no", "on", "off"}:
        raise ConfigurationError(f"{name} doit être un booléen (true/false).")
    return value in {"true", "1", "yes", "on"}


@dataclass(frozen=True)
class Settings:
    fire_confidence_threshold: float = _float("FIRE_CONFIDENCE_THRESHOLD", "0.95")
    fire_event_threshold: float = _float(
        "FIRE_EVENT_THRESHOLD", os.getenv("FIRE_CONFIDENCE_THRESHOLD", "0.95")
    )
    detection_cooldown_seconds: int = _int("DETECTION_COOLDOWN_SECONDS", "15", 0)
    required_positive_frames: int = _int("REQUIRED_POSITIVE_FRAMES", "3")
    prediction_debug: bool = _bool("PREDICTION_DEBUG")

    def database_url(self) -> URL:
        required = {
            name: os.getenv(name)
            for name in ("DB_HOST", "DB_NAME", "DB_USER")
        }
        missing = [name for name, value in required.items() if not value]
        if missing:
            raise ConfigurationError(
                "Configuration MySQL incomplète. Variables manquantes : "
                + ", ".join(missing)
            )
        port = _int("DB_PORT", "3306")
        return URL.create(
            drivername="mysql+pymysql",
            username=required["DB_USER"],
            password=os.getenv("DB_PASSWORD", ""),
            host=required["DB_HOST"],
            port=port,
            database=required["DB_NAME"],
            query={"charset": "utf8mb4"},
        )


settings = Settings()
FIRE_CONFIDENCE_THRESHOLD = settings.fire_confidence_threshold
