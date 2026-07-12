"""Journalisation partagée, sans données de connexion sensibles."""
import logging
from pathlib import Path

from config import PROJECT_ROOT

LOG_DIR = PROJECT_ROOT / "logs"
LOG_DIR.mkdir(parents=True, exist_ok=True)


def configure_logging() -> None:
    root = logging.getLogger()
    if any(isinstance(handler, logging.FileHandler) for handler in root.handlers):
        return
    root.setLevel(logging.INFO)
    formatter = logging.Formatter("%(asctime)s | %(levelname)s | %(name)s | %(message)s")
    handler = logging.FileHandler(LOG_DIR / "app.log", encoding="utf-8")
    handler.setFormatter(formatter)
    root.addHandler(handler)


configure_logging()
