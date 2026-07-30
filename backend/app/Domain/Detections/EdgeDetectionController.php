<?php

namespace App\Domain\Detections;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use Illuminate\Http\JsonResponse;

class EdgeDetectionController extends Controller
{
    public function __invoke(
        EdgeDetectionRequest $request,
        Camera $camera,
        EdgeDetectionService $service,
    ): JsonResponse {
        $client = $request->attributes->get('api_client');
        abort_unless($camera->factory_id === $client->factory_id, 403);
        abort_unless($camera->is_active, 422, __('phase2.camera.disabled'));

        $result = $service->receive(
            $camera,
            $client,
            $request->validated(),
            $request->file('snapshot'),
            $request,
        );

        return response()->json([
            'data' => [
                'status' => $result['receipt'],
                'event_id' => $result['event']->id,
                'event_uuid' => $result['event']->event_uuid,
                'workflow_status' => $result['event']->status->value,
            ],
        ], $result['receipt'] === 'created' ? 201 : 200);
    }
}
