<?php

namespace Tests\Feature\Security;

use App\Actions\Auth\IssueApiToken;
use App\Domain\Media\IncidentMediaService;
use App\Models\Camera;
use App\Models\Factory;
use App\Models\FireEvent;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FileUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    public function test_viewer_and_foreign_site_user_cannot_upload_incident_media(): void
    {
        [$viewer, $factoryA] = $this->localSiteUser('viewer', 'a');
        [, $factoryB, $eventB] = $this->eventFixture('b');
        $token = app(IssueApiToken::class)->execute($viewer);

        $this->withToken($token)->withHeader('X-Factory-ID', $factoryA->id)
            ->postJson("/api/factories/{$factoryA->id}/events/{$eventB->id}/media", [
                'image' => UploadedFile::fake()->image('probe.jpg'),
            ])->assertForbidden();

        $this->withToken($token)->withHeader('X-Factory-ID', $factoryB->id)
            ->postJson("/api/factories/{$factoryB->id}/events/{$eventB->id}/media", [
                'image' => UploadedFile::fake()->image('probe.jpg'),
            ])->assertForbidden();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_rejects_executable_mime_mismatch_and_oversized_files(): void
    {
        [$owner, $factory, $event] = $this->eventFixture('secure');
        $token = app(IssueApiToken::class)->execute($owner);
        $url = "/api/factories/{$factory->id}/events/{$event->id}/media";

        $this->withToken($token)->withHeader('X-Factory-ID', $factory->id)
            ->postJson($url, ['image' => UploadedFile::fake()->create('shell.php.jpg', 10, 'application/x-php')])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->withToken($token)->withHeader('X-Factory-ID', $factory->id)
            ->postJson($url, ['image' => UploadedFile::fake()->create('large.jpg', 10241, 'image/jpeg')])
            ->assertUnprocessable()->assertJsonValidationErrors('image');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_incident_replacement_uses_random_private_path_and_deletes_old_file(): void
    {
        [, , $event] = $this->eventFixture('replace');
        Storage::disk('local')->put('incidents/old.jpg', 'old');
        $event->update(['image_path' => 'incidents/old.jpg']);

        $updated = app(IncidentMediaService::class)->store($event, UploadedFile::fake()->image('../avatar.jpg'), null);

        Storage::disk('local')->assertMissing('incidents/old.jpg');
        Storage::disk('local')->assertExists($updated->image_path);
        $this->assertStringStartsWith("incidents/{$event->factory_id}/{$event->id}/", $updated->image_path);
        $this->assertStringNotContainsString('..', $updated->image_path);
    }

    public function test_private_incident_media_requires_same_local_site_permission(): void
    {
        [$owner, $factory, $event] = $this->eventFixture('download');
        $path = "incidents/{$factory->id}/{$event->id}/image.jpg";
        Storage::disk('local')->put($path, 'private-image');
        $event->update(['image_path' => $path]);

        $this->actingAs($owner)->withSession(['factory_id' => $factory->id])
            ->get(route('events.media', [$event, 'image']))->assertOk();

        [$other, $otherFactory] = $this->localSiteUser('factory_admin', 'other-download');
        $this->actingAs($other)->withSession(['factory_id' => $otherFactory->id])
            ->get(route('events.media', [$event, 'image']))->assertForbidden();
        $this->get(route('events.media', [$event, 'document']))->assertNotFound();
    }

    private function localSiteUser(string $roleName, string $suffix): array
    {
        $factory = Factory::create(['name' => "Factory {$suffix}", 'slug' => "factory-{$suffix}"]);
        $user = User::factory()->create(['is_active' => true]);
        $role = Role::where('name', $roleName)->firstOrFail();
        $factory->users()->attach($user, ['role' => $roleName, 'status' => 'active']);
        $user->roles()->attach($role, ['factory_id' => $factory->id]);

        return [$user, $factory];
    }

    private function eventFixture(string $suffix): array
    {
        [$owner, $factory] = $this->localSiteUser('factory_admin', $suffix);
        $camera = Camera::forceCreate(['factory_id' => $factory->id, 'name' => 'Camera', 'reference' => "C-{$suffix}", 'status' => 'online']);
        $event = FireEvent::forceCreate([
            'factory_id' => $factory->id, 'camera_id' => $camera->id, 'event_uuid' => (string) Str::uuid(),
            'type' => 'fire', 'severity' => 'high', 'status' => 'detected', 'confidence' => .9, 'detected_at' => now(),
        ]);

        return [$owner, $factory, $event];
    }
}
