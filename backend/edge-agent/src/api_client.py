from __future__ import annotations

from dataclasses import dataclass
import logging
import mimetypes
from pathlib import Path
import time
from urllib.parse import urlsplit, urlunsplit

import requests

from .retry_policy import RetryPolicy

logger = logging.getLogger(__name__)
RETRYABLE_STATUS = {429, 500, 502, 503, 504}
PERMANENT_STATUS = {400, 401, 403, 404, 409, 422}


@dataclass(frozen=True)
class ApiResult:
    status_code: int
    receipt: str
    data: dict


class ApiError(RuntimeError):
    def __init__(self, message: str, status_code: int | None = None, retryable: bool = False):
        super().__init__(message)
        self.status_code, self.retryable = status_code, retryable


class EdgeApiClient:
    def __init__(self, base_url: str, token: str, camera_id: str, verify_tls: bool = True,
                 connect_timeout: float = 5, read_timeout: float = 15,
                 retry_policy: RetryPolicy | None = None, sleep=time.sleep,
                 heartbeat_timeout: float | None = None):
        self.base_url, self.camera_id, self.verify_tls = base_url.rstrip("/"), camera_id, verify_tls
        self.timeout = (connect_timeout, read_timeout)
        self.heartbeat_timeout = (connect_timeout, heartbeat_timeout or read_timeout)
        self.retry_policy = retry_policy or RetryPolicy()
        self.sleep = sleep
        self.session = requests.Session()
        self.session.headers.update({"Authorization": f"Bearer {token}", "Accept": "application/json", "User-Agent": "detect-incendie-edge/1.0"})

    def send_detection(self, payload: dict, snapshot: Path | None = None) -> ApiResult:
        return self._request("POST", f"edge/cameras/{self.camera_id}/detections", payload, snapshot)

    def upload_snapshot(self, payload: dict, snapshot: Path) -> ApiResult:
        return self.send_detection(payload, snapshot)

    def send_heartbeat(self, payload: dict, retry: bool = True) -> ApiResult:
        return self._request("POST", f"edge/cameras/{self.camera_id}/heartbeat", payload,
                             timeout=self.heartbeat_timeout,
                             retry_budget=self.retry_policy.max_retries if retry else 0)

    def health_check(self) -> ApiResult:
        parsed = urlsplit(self.base_url)
        root = urlunsplit((parsed.scheme, parsed.netloc, "", "", "")).rstrip("/")
        return self._request("GET", f"{root}/up", None, absolute=True)

    def _request(self, method: str, endpoint: str, payload: dict | None, snapshot: Path | None = None,
                 timeout: tuple[float, float] | None = None, absolute: bool = False,
                 retry_budget: int | None = None) -> ApiResult:
        budget = self.retry_policy.max_retries if retry_budget is None else retry_budget
        for attempt in range(budget + 1):
            handle = None
            try:
                kwargs: dict = {"timeout": timeout or self.timeout, "verify": self.verify_tls}
                if snapshot:
                    if not snapshot.is_file():
                        raise ApiError("Snapshot does not exist", retryable=False)
                    handle = snapshot.open("rb")
                    mime = mimetypes.guess_type(snapshot.name)[0] or "application/octet-stream"
                    kwargs["files"] = {"snapshot": (snapshot.name, handle, mime)}
                    form = {key: value for key, value in (payload or {}).items() if key != "bounding_box"}
                    form.update({f"bounding_box[{key}]": value for key, value in (payload or {}).get("bounding_box", {}).items()})
                    kwargs["data"] = form
                elif payload is not None:
                    kwargs["json"] = payload
                url = endpoint if absolute else f"{self.base_url}/{endpoint.lstrip('/')}"
                response = self.session.request(method, url, **kwargs)
                if response.status_code in RETRYABLE_STATUS and attempt < budget:
                    self.sleep(self.retry_policy.delay(attempt + 1, response.headers.get("Retry-After")))
                    continue
                if response.status_code >= 400:
                    raise ApiError(f"API returned HTTP {response.status_code}", response.status_code,
                                   response.status_code in RETRYABLE_STATUS)
                try:
                    body = response.json()
                except ValueError:
                    body = {}
                data = body.get("data", body)
                receipt = data.get("status", "ok") if isinstance(data, dict) else "ok"
                return ApiResult(response.status_code, receipt, data if isinstance(data, dict) else {})
            except (requests.Timeout, requests.ConnectionError) as exc:
                if attempt >= budget:
                    raise ApiError(exc.__class__.__name__, retryable=True) from exc
                self.sleep(self.retry_policy.delay(attempt + 1))
            finally:
                if handle:
                    handle.close()
        raise ApiError("Retry budget exhausted", retryable=True)

    def close(self) -> None:
        self.session.close()
