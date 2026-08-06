from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
from pathlib import Path
import tempfile
import threading
import unittest

from src.api_client import EdgeApiClient
from src.retry_policy import RetryPolicy


class EdgeHandler(BaseHTTPRequestHandler):
    requests = []
    detection_attempts = 0

    def do_GET(self):
        self._record()
        self._json(200, {"data": {"status": "healthy"}})

    def do_POST(self):
        body = self._record()
        if self.path.endswith("/detections"):
            type(self).detection_attempts += 1
            if type(self).detection_attempts == 1:
                self._json(503, {"message": "temporary"}, {"Retry-After": "0"})
            else:
                self._json(201, {"data": {"status": "created"}})
        else:
            self._json(200, {"data": {"status": "online"}})

    def _record(self):
        length = int(self.headers.get("Content-Length", "0")); body = self.rfile.read(length)
        type(self).requests.append((self.command, self.path, dict(self.headers), body))
        return body

    def _json(self, status, payload, headers=None):
        body = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        for key, value in (headers or {}).items(): self.send_header(key, value)
        self.end_headers(); self.wfile.write(body)

    def log_message(self, *_):
        pass


class HttpIntegrationTests(unittest.TestCase):
    def setUp(self):
        EdgeHandler.requests = []; EdgeHandler.detection_attempts = 0
        self.server = ThreadingHTTPServer(("127.0.0.1", 0), EdgeHandler)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True); self.thread.start()
        port = self.server.server_address[1]
        self.client = EdgeApiClient(f"http://127.0.0.1:{port}/api", "client.secret", "camera-1",
            retry_policy=RetryPolicy(1, .01, .01), sleep=lambda _: None)

    def tearDown(self):
        self.client.close(); self.server.shutdown(); self.server.server_close(); self.thread.join(1)

    def test_real_http_multipart_retry_bearer_and_health_path(self):
        payload = {"event_uuid": "stable-uuid", "bounding_box": {"x": .1, "y": .2, "width": .3, "height": .4}}
        with tempfile.TemporaryDirectory() as directory:
            snapshot = Path(directory) / "snapshot.jpg"; snapshot.write_bytes(b"jpeg-bytes")
            result = self.client.send_detection(payload, snapshot)
        self.assertEqual((result.status_code, result.receipt), (201, "created"))
        detections = [item for item in EdgeHandler.requests if item[1].endswith("/detections")]
        self.assertEqual(len(detections), 2)
        for _, path, headers, body in detections:
            self.assertEqual(path, "/api/edge/cameras/camera-1/detections")
            self.assertEqual(headers["Authorization"], "Bearer client.secret")
            self.assertIn("multipart/form-data", headers["Content-Type"])
            self.assertIn(b"stable-uuid", body); self.assertIn(b"jpeg-bytes", body)
        health = self.client.health_check()
        self.assertEqual(health.receipt, "healthy")
        self.assertEqual(EdgeHandler.requests[-1][1], "/up")


if __name__ == "__main__": unittest.main()
