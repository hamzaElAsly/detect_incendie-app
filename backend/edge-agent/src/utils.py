from __future__ import annotations

from datetime import datetime, timezone
from urllib.parse import urlsplit, urlunsplit


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def redact_url(value: str) -> str:
    try:
        parsed = urlsplit(value)
        if not parsed.username and not parsed.password:
            return value
        host = parsed.hostname or ""
        if parsed.port:
            host += f":{parsed.port}"
        return urlunsplit((parsed.scheme, f"***:***@{host}", parsed.path, parsed.query, parsed.fragment))
    except ValueError:
        return "<redacted-source>"
