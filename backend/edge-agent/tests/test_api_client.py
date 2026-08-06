import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import Mock

import requests

from src.api_client import ApiError, EdgeApiClient
from src.retry_policy import RetryPolicy


class ApiClientTests(unittest.TestCase):
    def client(self):
        client = EdgeApiClient("https://api.test/api", "sensitive-token", "cam-1", retry_policy=RetryPolicy(1, .01, .01), sleep=lambda _: None)
        client.session.request = Mock()
        return client

    def response(self, status, body=None, headers=None):
        response = Mock(status_code=status, headers=headers or {})
        response.json.return_value = body or {"data": {"status": "created"}}
        return response

    def test_created_and_already_received_are_success(self):
        client = self.client()
        client.session.request.side_effect = [self.response(201), self.response(200, {"data": {"status": "already_received"}})]
        self.assertEqual(client.send_detection({"event_uuid": "x"}).receipt, "created")
        self.assertEqual(client.send_detection({"event_uuid": "x"}).receipt, "already_received")

    def test_retryable_status_and_timeout_retry(self):
        client = self.client()
        client.session.request.side_effect = [self.response(503), self.response(201)]
        self.assertEqual(client.send_detection({}).status_code, 201)
        client.session.request.side_effect = [requests.Timeout(), self.response(200)]
        self.assertEqual(client.send_heartbeat({}).status_code, 200)

    def test_shutdown_heartbeat_can_disable_retries(self):
        client = self.client(); client.session.request.return_value = self.response(503)
        with self.assertRaises(ApiError): client.send_heartbeat({}, retry=False)
        self.assertEqual(client.session.request.call_count, 1)

    def test_permanent_status_is_not_retried(self):
        client = self.client(); client.session.request.return_value = self.response(422)
        with self.assertRaises(ApiError) as caught:
            client.send_detection({})
        self.assertFalse(caught.exception.retryable)
        self.assertEqual(client.session.request.call_count, 1)

    def test_multipart_flattens_box_and_closes_file(self):
        client = self.client(); client.session.request.return_value = self.response(201)
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "snapshot.jpg"; path.write_bytes(b"jpeg")
            client.send_detection({"bounding_box": {"x": .1, "y": .2, "width": .3, "height": .4}}, path)
            kwargs = client.session.request.call_args.kwargs
            self.assertEqual(kwargs["data"]["bounding_box[x]"], .1)
            self.assertTrue(kwargs["files"]["snapshot"][1].closed)

    def test_missing_snapshot_is_permanent(self):
        client = self.client()
        with self.assertRaises(ApiError) as caught:
            client.send_detection({}, Path("missing.jpg"))
        self.assertFalse(caught.exception.retryable)
        client.session.request.assert_not_called()


if __name__ == "__main__": unittest.main()
