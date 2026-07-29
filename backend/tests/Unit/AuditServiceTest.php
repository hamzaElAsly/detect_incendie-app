<?php

namespace Tests\Unit;

use App\Domain\Audit\AuditService;
use App\Models\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_secrets_are_redacted(): void
    {
        $factory = Factory::create(['name' => 'A', 'slug' => 'a']);
        $log = app(AuditService::class)->record('camera.credentials.updated', 'camera', [], ['password' => 'secret', 'name' => 'Camera'], $factory);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertSame('Camera', $log->new_values['name']);
    }
}
