<?php

namespace App\Domain\Detections;

use App\Domain\Audit\AuditService;
use App\Models\Alert;
use App\Models\ApiClient;
use App\Models\Camera;
use App\Models\FireEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EdgeDetectionService
{
    public function __construct(private readonly AuditService $audit) {}

    public function receive(
        Camera $camera,
        ApiClient $client,
        array $data,
        ?UploadedFile $snapshot,
        Request $request,
    ): array {
        $existing = FireEvent::withTrashed()->where('event_uuid', $data['event_uuid'])->first();
        if ($existing) {
            abort_unless($existing->factory_id === $client->factory_id && $existing->camera_id === $camera->id, 409);

            return ['event' => $existing, 'receipt' => 'already_received'];
        }

        $snapshotPath = $snapshot?->storeAs(
            "incidents/{$camera->factory_id}",
            Str::ulid().'.'.$snapshot->guessExtension(),
            config('filesystems.default'),
        );

        try {
            $event = DB::transaction(function () use ($camera, $client, $data, $snapshotPath, $request): FireEvent {
                $box = $data['bounding_box'];
                $metadata = [
                    'source' => 'edge_agent',
                    'api_client_id' => $client->id,
                    'model_name' => $data['model_name'],
                    'model_version' => $data['model_version'],
                    'class_id' => $data['class_id'],
                    'frame_width' => $data['frame_width'],
                    'frame_height' => $data['frame_height'],
                    'inference_ms' => (float) $data['inference_ms'],
                    'confirmation_frames' => $data['confirmation_frames'],
                ];
                $event = FireEvent::forceCreate([
                    'factory_id' => $camera->factory_id,
                    'camera_id' => $camera->id,
                    'zone_id' => $camera->zone_id,
                    'event_uuid' => $data['event_uuid'],
                    'type' => $data['class'],
                    'severity' => $data['class'] === 'fire' ? 'high' : 'medium',
                    'status' => 'detected',
                    'confidence' => $data['confidence'],
                    'detected_at' => $data['detected_at'],
                    'image_path' => $snapshotPath,
                    'metadata' => $metadata,
                ]);
                $event->detections()->create([
                    'class_name' => $data['class'],
                    'confidence' => $data['confidence'],
                    'x1' => $box['x'],
                    'y1' => $box['y'],
                    'x2' => (float) $box['x'] + (float) $box['width'],
                    'y2' => (float) $box['y'] + (float) $box['height'],
                    'metadata' => $metadata,
                ]);
                Alert::forceCreate([
                    'factory_id' => $camera->factory_id,
                    'fire_event_id' => $event->id,
                    'type' => $data['class'],
                    'severity' => $data['class'] === 'fire' ? 'high' : 'medium',
                    'status' => 'pending',
                    'message' => "Edge detection awaiting human validation: {$data['class']}",
                ]);
                $this->audit->record(
                    'fire_event.created.edge',
                    $event,
                    [],
                    [
                        'camera_id' => $camera->id,
                        'event_uuid' => $event->event_uuid,
                        'class' => $data['class'],
                        'confidence' => (float) $data['confidence'],
                        'model_version' => $data['model_version'],
                    ],
                    $camera->factory,
                    null,
                    $request,
                );

                return $event;
            });
        } catch (QueryException $exception) {
            if ($snapshotPath) {
                Storage::disk(config('filesystems.default'))->delete($snapshotPath);
            }
            $existing = FireEvent::withTrashed()->where('event_uuid', $data['event_uuid'])->first();
            if ($existing && $existing->factory_id === $client->factory_id && $existing->camera_id === $camera->id) {
                return ['event' => $existing, 'receipt' => 'already_received'];
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($snapshotPath) {
                Storage::disk(config('filesystems.default'))->delete($snapshotPath);
            }
            throw $exception;
        }

        return ['event' => $event, 'receipt' => 'created'];
    }
}
