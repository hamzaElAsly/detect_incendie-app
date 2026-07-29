<?php

namespace App\Domain\Health;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use App\Models\CameraHealthLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CameraHeartbeatController extends Controller
{
    public function __invoke(HeartbeatRequest $request, Camera $camera, CameraStatusService $statuses): JsonResponse
    {
        $client = $request->attributes->get('api_client');
        abort_unless($camera->factory_id === $client->factory_id, 403);
        abort_unless($camera->is_active, 422, __('phase2.camera.disabled'));

        $data = $request->validated();
        $previous = $statuses->status($camera);
        DB::transaction(function () use ($camera, $client, $data, $previous): void {
            $receivedAt = now();
            $camera->forceFill([
                'last_heartbeat_at' => $receivedAt,
                'last_seen_at' => $receivedAt,
                'last_frame_at' => $data['last_frame_at'] ?? $camera->last_frame_at,
                'latency_ms' => $data['latency_ms'] ?? null,
                'fps' => $data['fps'] ?? null,
                'resolution' => $data['resolution'] ?? null,
                'edge_agent_version' => $data['edge_agent_version'] ?? null,
                'model_version' => $data['model_version'] ?? null,
                'last_error_code' => $data['error_code'] ?? null,
                'last_error_message' => $data['error_message'] ?? null,
                'last_api_client_id' => $client->id,
            ])->save();

            $current = app(CameraStatusService::class)->status($camera->refresh());
            $latest = $camera->healthLogs()->latest('recorded_at')->first();
            if (! $latest || $latest->status !== $current || $previous !== $current) {
                CameraHealthLog::forceCreate([
                    'factory_id' => $camera->factory_id,
                    'camera_id' => $camera->id,
                    'status' => $current,
                    'latency_ms' => $data['latency_ms'] ?? null,
                    'fps' => $data['fps'] ?? null,
                    'last_frame_at' => $data['last_frame_at'] ?? null,
                    'message' => $data['error_message'] ?? null,
                    'recorded_at' => $receivedAt,
                ]);
            }
        });

        return response()->json([
            'data' => [
                'camera_id' => $camera->id,
                'status' => $statuses->status($camera->refresh()),
                'received_at' => $camera->last_heartbeat_at->toIso8601String(),
            ],
        ]);
    }
}
