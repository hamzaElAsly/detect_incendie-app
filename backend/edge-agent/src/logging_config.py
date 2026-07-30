from __future__ import annotations

import logging
from logging.handlers import RotatingFileHandler
from pathlib import Path


def configure_logging(path: Path, level: int = logging.INFO) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    formatter = logging.Formatter("%(asctime)s %(levelname)s %(name)s %(message)s")
    handler = RotatingFileHandler(path, maxBytes=5_000_000, backupCount=5, encoding="utf-8")
    handler.setFormatter(formatter)
    root = logging.getLogger()
    root.setLevel(level)
    root.handlers[:] = [handler]
