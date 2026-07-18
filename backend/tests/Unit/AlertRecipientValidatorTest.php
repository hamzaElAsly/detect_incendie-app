<?php

namespace Tests\Unit;

use App\Domain\Recipients\AlertRecipientValidator;
use App\Models\Factory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AlertRecipientValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_details_are_required_by_selected_channel(): void
    {
        $factory = Factory::create(['name' => 'Factory', 'slug' => 'factory']);
        $this->expectException(ValidationException::class);
        app(AlertRecipientValidator::class)->validate($factory, [
            'name' => 'Security', 'channels' => ['email'], 'severity_min' => 'high', 'is_active' => true,
        ]);
    }

    public function test_user_must_belong_to_the_current_factory(): void
    {
        $factory = Factory::create(['name' => 'Factory', 'slug' => 'factory']);
        $other = Factory::create(['name' => 'Other', 'slug' => 'other']);
        $user = User::factory()->create();
        $other->users()->attach($user, ['role' => 'viewer']);

        $this->expectException(ValidationException::class);
        app(AlertRecipientValidator::class)->validate($factory, [
            'user_id' => $user->id, 'name' => 'Viewer', 'email' => 'viewer@example.com',
            'channels' => ['email'], 'severity_min' => 'low', 'is_active' => true,
        ]);
    }
}
