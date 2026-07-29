<?php

namespace App\Domain\Dashboard;

use App\Models\Factory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController
{
    public function __construct(private DashboardService $dashboard) {}

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Dashboard retrieved successfully', 'data' => $data, 'meta' => (object) []]);
    }

    public function summary(Factory $factory)
    {
        return $this->ok($this->dashboard->summary($factory));
    }

    public function eventsChart(Request $r, Factory $factory)
    {
        return $this->ok($this->dashboard->eventsChart($factory, (int) $r->integer('days', 30)));
    }

    public function cameraStatus(Factory $factory)
    {
        return $this->ok($this->dashboard->cameraStatus($factory));
    }

    public function recentEvents(Request $r, Factory $factory)
    {
        return $this->ok($this->dashboard->recentEvents($factory, (int) $r->integer('limit', 10)));
    }

    public function metrics(Factory $factory)
    {
        return $this->ok($this->dashboard->metrics($factory));
    }
}
