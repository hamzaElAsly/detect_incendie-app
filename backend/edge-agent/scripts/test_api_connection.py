from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from src.api_client import ApiError, EdgeApiClient
from src.retry_policy import RetryPolicy
from src.settings import EdgeSettings


def main() -> int:
    parser = argparse.ArgumentParser(description="Send one Laravel-compatible diagnostic heartbeat")
    parser.add_argument("--status", choices=["online", "degraded", "error"], default="degraded")
    args = parser.parse_args()
    settings = EdgeSettings()
    if not settings.api_base_url or not settings.api_client_token or not settings.camera_id:
        print("EDGE_API_BASE_URL, EDGE_API_CLIENT_TOKEN and EDGE_CAMERA_ID are required", file=sys.stderr)
        return 2
    client = EdgeApiClient(settings.api_base_url, settings.api_client_token, settings.camera_id,
        settings.verify_tls, settings.api_connect_timeout_seconds, settings.api_read_timeout_seconds,
        RetryPolicy(settings.api_max_retries, settings.api_retry_initial_seconds, settings.api_retry_max_seconds),
        heartbeat_timeout=settings.heartbeat_timeout_seconds)
    try:
        result = client.send_heartbeat({"status": args.status, "edge_agent_version": "connection-test"})
        print(f"OK http_status={result.status_code} camera_status={result.receipt}")
        return 0
    except ApiError as exc:
        print(f"FAILED http_status={exc.status_code or 'network'} retryable={exc.retryable}", file=sys.stderr)
        return 1
    finally:
        client.close()


if __name__ == "__main__":
    raise SystemExit(main())
