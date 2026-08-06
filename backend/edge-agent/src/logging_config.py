from __future__ import annotations

import json
import logging
from logging.handlers import RotatingFileHandler
from pathlib import Path
import re


class SecretFilter(logging.Filter):
    PATTERNS = [
        (re.compile(r"(?i)(authorization\s*[=:]\s*)(?:bearer\s+)?[^\s,;]+"), r"\1<redacted>"),
        (re.compile(r"(?i)((?:token|secret|password)\s*[=:]\s*)[^\s,;]+"), r"\1<redacted>"),
        (re.compile(r"(?i)(bearer\s+)[^\s,;]+"), r"\1<redacted>"),
        (re.compile(r"(?i)(rtsp[s]?://)[^/@\s]+@"), r"\1<redacted>@"),
    ]

    def filter(self, record: logging.LogRecord) -> bool:
        message = record.getMessage()
        for pattern, replacement in self.PATTERNS:
            message = pattern.sub(replacement, message)
        record.msg, record.args = message, ()
        return True


class JsonFormatter(logging.Formatter):
    def format(self, record: logging.LogRecord) -> str:
        return json.dumps({"time": self.formatTime(record), "level": record.levelname,
                           "logger": record.name, "message": record.getMessage()}, ensure_ascii=False)


def configure_logging(path: Path, level: int | str = logging.INFO, max_bytes: int = 10_485_760,
                      backup_count: int = 5, console: bool = True) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    formatter, secret_filter = JsonFormatter(), SecretFilter()
    handlers: list[logging.Handler] = [RotatingFileHandler(path, maxBytes=max_bytes, backupCount=backup_count, encoding="utf-8")]
    if console:
        handlers.append(logging.StreamHandler())
    for handler in handlers:
        handler.setFormatter(formatter)
        handler.addFilter(secret_filter)
    root = logging.getLogger()
    root.setLevel(level)
    root.handlers[:] = handlers
