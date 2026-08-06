import unittest

from src.temporal_filter import DetectionState, TemporalDetectionFilter


class TemporalFilterTests(unittest.TestCase):
    def setUp(self):
        self.filter = TemporalDetectionFilter({"fire": .9, "smoke": .85}, 3, 5, 10, 6)

    def test_isolated_detection_is_not_confirmed(self):
        result = self.filter.observe("fire", .95, 1)
        self.assertEqual(result.state, DetectionState.CANDIDATE)

    def test_three_frames_confirm_then_cooldown(self):
        self.filter.observe("fire", .95, 1)
        self.filter.observe("fire", .95, 2)
        self.assertEqual(self.filter.observe("fire", .95, 3).state, DetectionState.CONFIRMED)
        self.assertEqual(self.filter.observe("fire", .95, 4).state, DetectionState.COOLDOWN)

    def test_window_expiry_and_low_confidence(self):
        self.filter.observe("fire", .95, 1)
        self.filter.observe("fire", .95, 7)
        self.assertEqual(self.filter.observe("fire", .2, 8).state, DetectionState.REJECTED)

    def test_classes_are_independent_and_event_ends(self):
        self.filter.observe("fire", .95, 1)
        self.assertEqual(self.filter.observe("smoke", .9, 2).positive_frames, 1)
        self.assertEqual(self.filter.observe("fire", .1, 8).state, DetectionState.ENDED)

    def test_reset_after_reconnection_allows_fresh_event(self):
        self.filter.observe("fire", .95, 1)
        self.filter.observe("fire", .95, 2)
        self.filter.reset()
        self.assertEqual(self.filter.observe("fire", .95, 3).positive_frames, 1)

    def test_event_end_then_new_event(self):
        for observed_at in (1, 2): self.filter.observe("fire", .95, observed_at)
        self.assertEqual(self.filter.observe("fire", .95, 3).state, DetectionState.CONFIRMED)
        self.assertEqual(self.filter.observe("fire", 0, 9).state, DetectionState.ENDED)
        for observed_at in (10, 11): self.filter.observe("fire", .95, observed_at)
        self.assertEqual(self.filter.observe("fire", .95, 12).state, DetectionState.CONFIRMED)


if __name__ == "__main__":
    unittest.main()
