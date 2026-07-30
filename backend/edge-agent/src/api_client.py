from __future__ import annotations

import logging
from pathlib import Path

import requests

logger = logging.getLogger(__name__)


class EdgeApiClient:
    def __init__(self, base_url: str, token: str, camera_id: str, verify_tls: bool = True):
        self.base_url, self.camera_id, self.verify_tls = base_url.rstrip("/"), camera_id, verify_tls
        self.session = requests.Session()
        self.session.headers.update({"Authorization": f"Bearer {token}", "Accept": "application/json"})

    def send_detection(self, payload: dict, snapshot: Path | None = None) -> str:
        files = None
        handle = None
        try:
            if snapshot:
                handle = snapshot.open("rb")
                files = {"snapshot": (snapshot.name, handle, "image/jpeg")}
            form_payload = payload
            if files and isinstance(payload.get("bounding_box"), dict):
                form_payload = {key: value for key, value in payload.items() if key != "bounding_box"}
                form_payload.update({
                    f"bounding_box[{key}]": value
                    for key, value in payload["bounding_box"].items()
                })
            response = self.session.post(
                f"{self.base_url}/edge/cameras/{self.camera_id}/detections",
                data=form_payload if files else None,
                json=None if files else payload,
                files=files,
                timeout=(5, 30),
                verify=self.verify_tls,
            )
            response.raise_for_status()
            return response.json()["data"]["status"]
        finally:
            if handle:
                handle.close()

    def send_heartbeat(self, payload: dict) -> None:
        response = self.session.post(
            f"{self.base_url}/edge/cameras/{self.camera_id}/heartbeat",
            json=payload,
            timeout=(5, 15),
            verify=self.verify_tls,
        )
        response.raise_for_status()
