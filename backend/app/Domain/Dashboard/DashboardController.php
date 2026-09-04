<?php

namespace App\Domain\Dashboard;

use App\Services\FactoryContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController
{
    public function __construct(private DashboardService $dashboard) {}

    private function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Dashboard retrieved successfully', 'data' => $data, 'meta' => (object) []]);
    }

    public function summary(FactoryContext $context)
    {
        return $this->ok($this->dashboard->summary($context->get()));
    }

    public function eventsChart(Request $r, FactoryContext $context)
    {
        return $this->ok($this->dashboard->eventsChart($context->get(), (int) $r->integer('days', 30)));
    }

    public function cameraStatus(FactoryContext $context)
    {
        return $this->ok($this->dashboard->cameraStatus($context->get()));
    }

    public function recentEvents(Request $r, FactoryContext $context)
    {
        return $this->ok($this->dashboard->recentEvents($context->get(), (int) $r->integer('limit', 10)));
    }

    public function metrics(FactoryContext $context)
    {
        return $this->ok($this->dashboard->metrics($context->get()));
    }
}
