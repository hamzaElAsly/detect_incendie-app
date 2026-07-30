<?php

namespace Tests\Feature;

use App\Domain\ApiClients\ApiClientService;
use App\Models\Camera;
use App\Models\Factory;
use App\Models\FireEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase4EdgeDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_detection_creates_event_detection_alert_and_audit(): void
    {
        [$factory, $camera, $token] = $this->edgeContext();
        $uuid = (string) Str::uuid();

        $this->withToken($token)
            ->postJson(route('edge.cameras.detections', $camera), $this->payload($uuid))
            ->assertCreated()
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('data.event_uuid', $uuid)
            ->assertJsonPath('data.workflow_status', 'detected');

        $event = FireEvent::where('event_uuid', $uuid)->firstOrFail();
        $this->assertSame($factory->id, $event->factory_id);
        $this->assertSame($camera->id, $event->camera_id);
        $this->assertSame('fire-smoke-yolo', $event->metadata['model_name']);
        $this->assertDatabaseHas('fire_event_detections', ['fire_event_id' => $event->id, 'class_name' => 'fire']);
        $this->assertDatabaseHas('alerts', ['fire_event_id' => $event->id, 'status' => 'pending']);
        $this->assertDatabaseHas('audit_logs', ['factory_id' => $factory->id, 'action' => 'fire_event.created.edge']);
    }

    public function test_repeated_event_uuid_is_idempotent(): void
    {
        [, $camera, $token] = $this->edgeContext();
        $uuid = (string) Str::uuid();

        $this->withToken($token)->postJson(route('edge.cameras.detections', $camera), $this->payload($uuid))->assertCreated();
        $this->withToken($token)->postJson(route('edge.cameras.detections', $camera), $this->payload($uuid))
            ->assertOk()
            ->assertJsonPath('data.status', 'already_received');

        $this->assertSame(1, FireEvent::where('event_uuid', $uuid)->count());
        $this->assertDatabaseCount('fire_event_detections', 1);
        $this->assertDatabaseCount('alerts', 1);
    }

    public function test_detection_requires_authentication_scope_and_active_client(): void
    {
        [$factory, $camera] = $this->edgeContext();
        $payload = $this->payload();

        $this->postJson(route('edge.cameras.detections', $camera), $payload)->assertUnauthorized();
        $unscoped = app(ApiClientService::class)->create($factory, ['name' => 'Heartbeat only', 'scopes' => ['camera:heartbeat']]);
        $this->withToken($unscoped->client->client_id.'.'.$unscoped->secret)
            ->postJson(route('edge.cameras.detections', $camera), $payload)
            ->assertForbidden();
        $expired = app(ApiClientService::class)->create($factory, ['name' => 'Expired', 'scopes' => ['event:create'], 'expires_at' => now()->subMinute()]);
        $this->withToken($expired->client->client_id.'.'.$expired->secret)
            ->postJson(route('edge.cameras.detections', $camera), $payload)
            ->assertForbidden();
    }

    public function test_detection_rejects_cross_tenant_and_disabled_camera(): void
    {
        [$factory, $camera, $token] = $this->edgeContext();
        $other = Factory::create(['name' => 'Other', 'slug' => 'other']);
        $foreign = Camera::forceCreate(['factory_id' => $other->id, 'name' => 'Foreign', 'reference' => 'FOREIGN', 'status' => 'online']);

        $this->withToken($token)->postJson(route('edge.cameras.detections', $foreign), $this->payload())->assertForbidden();
        $camera->update(['is_active' => false]);
        $this->withToken($token)->postJson(route('edge.cameras.detections', $camera), $this->payload())->assertUnprocessable();
        $this->assertDatabaseMissing('fire_events', ['factory_id' => $factory->id]);
    }

    public function test_detection_rejects_invalid_class_confidence_box_timestamp_and_unknown_fields(): void
    {
        [, $camera, $token] = $this->edgeContext();
        $cases = [
            ['class' => 'flame'],
            ['confidence' => 1.01],
            ['bounding_box' => ['x' => .9, 'y' => .2, 'width' => .2, 'height' => .2]],
            ['detected_at' => now()->subMinutes(6)->toIso8601String()],
            ['factory_id' => 'attacker-controlled'],
        ];

        foreach ($cases as $override) {
            $this->withToken($token)
                ->postJson(route('edge.cameras.detections', $camera), array_replace($this->payload(), $override))
                ->assertUnprocessable();
        }
        $this->assertDatabaseCount('fire_events', 0);
    }

    public function test_snapshot_is_validated_and_stored_privately_with_random_name(): void
    {
        Storage::fake('local');
        [, $camera, $token] = $this->edgeContext();
        $payload = $this->payload();
        $payload['snapshot'] = UploadedFile::fake()->image('predictable.jpg', 800, 600);

        $this->withToken($token)->post(route('edge.cameras.detections', $camera), $payload)->assertCreated();

        $event = FireEvent::firstOrFail();
        $this->assertStringStartsWith("incidents/{$camera->factory_id}/", $event->image_path);
        $this->assertStringNotContainsString('predictable', $event->image_path);
        Storage::disk('local')->assertExists($event->image_path);

        $invalid = $this->payload();
        $invalid['snapshot'] = UploadedFile::fake()->create('payload.jpg', 1, 'text/x-php');
        $this->withToken($token)->post(route('edge.cameras.detections', $camera), $invalid)->assertSessionHasErrors('snapshot');
    }

    private function edgeContext(): array
    {
        $factory = Factory::create(['name' => 'Alpha', 'slug' => 'alpha']);
        $camera = Camera::forceCreate([
            'factory_id' => $factory->id,
            'name' => 'Gate',
            'reference' => 'GATE-1',
            'status' => 'online',
            'is_active' => true,
        ]);
        $credentials = app(ApiClientService::class)->create($factory, [
            'name' => 'Edge Agent',
            'scopes' => ['camera:heartbeat', 'event:create'],
        ]);

        return [$factory, $camera, $credentials->client->client_id.'.'.$credentials->secret];
    }

    private function payload(?string $uuid = null): array
    {
        return [
            'event_uuid' => $uuid ?? (string) Str::uuid(),
            'detected_at' => now()->toIso8601String(),
            'class' => 'fire',
            'confidence' => .94,
            'bounding_box' => ['x' => .24, 'y' => .31, 'width' => .18, 'height' => .22],
            'frame_width' => 1920,
            'frame_height' => 1080,
            'model_name' => 'fire-smoke-yolo',
            'model_version' => 'v1',
            'inference_ms' => 32.4,
            'confirmation_frames' => 5,
        ];
    }
}
