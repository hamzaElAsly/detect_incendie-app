<?php

namespace App\Http\Controllers\Web\Seeders;

use App\Models\Alert;
use App\Models\AlertRecipient;
use App\Models\ApiClient;
use App\Models\Camera;
use App\Models\CameraHealthLog;
use App\Models\Factory;
use App\Models\FireEvent;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make((string) config('app.demo_password'));
        $factory = Factory::updateOrCreate(
            ['slug' => 'local-installation'],
            [
                'name' => 'Local Fire Detection Site',
                'reference' => 'LOCAL-INSTALLATION',
                'installation_identifier' => 'ONPREM-LOCAL',
                'city' => 'Casablanca',
                'country' => 'Morocco',
                'timezone' => 'Africa/Casablanca',
                'status' => 'active',
                'is_active' => true,
            ],
        );

        $accounts = [
            'system_admin' => 'sysadmin@firewatch.local',
            'factory_admin' => 'admin@firewatch.local',
            'factory_manager' => 'manager@firewatch.local',
            'safety_officer' => 'safety@firewatch.local',
            'security_agent' => 'security@firewatch.local',
            'maintenance_agent' => 'maintenance@firewatch.local',
            'viewer' => 'viewer@firewatch.local',
        ];

        $users = collect($accounts)->mapWithKeys(function ($email, $role) use ($password) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => str($role)->replace('_', ' ')->title(),
                    'password' => $password,
                    'is_active' => true,
                    'is_super_admin' => $role === 'system_admin',
                    'email_verified_at' => now(),
                ],
            );

            return [$role => $user];
        });

        $users->each(function (User $user, string $roleName) use ($factory): void {
            $role = Role::where('name', $roleName)->firstOrFail();
            $user->roles()->syncWithoutDetaching([$role->id => ['factory_id' => $role->is_platform ? null : $factory->id]]);

            if (! $role->is_platform) {
                $factory->users()->syncWithoutDetaching([
                    $user->id => ['role' => $roleName, 'status' => 'active', 'joined_at' => now()],
                ]);
            }
        });

        $factory->update(['owner_id' => $users['factory_admin']->id]);

        for ($z = 1; $z <= 2; $z++) {
            $zone = Zone::withTrashed()->firstOrCreate(
                ['factory_id' => $factory->id, 'reference' => 'Z-'.$z],
                ['name' => 'Zone '.$z, 'risk_level' => $z === 1 ? 'high' : 'medium', 'floor' => 'Level '.$z, 'status' => 'active'],
            );

            for ($c = 1; $c <= 2; $c++) {
                $camera = Camera::withTrashed()->firstOrCreate(
                    ['factory_id' => $factory->id, 'reference' => 'CAM-'.$z.$c],
                    ['zone_id' => $zone->id, 'name' => 'Camera '.$z.$c, 'brand' => 'Hikvision', 'model' => 'DS-2CD', 'status' => $c === 1 ? 'online' : 'offline', 'is_active' => true, 'fire_threshold' => .65, 'smoke_threshold' => .6],
                );

                for ($h = 0; $h < 8; $h++) {
                    CameraHealthLog::forceCreate([
                        'factory_id' => $factory->id,
                        'camera_id' => $camera->id,
                        'status' => $camera->status,
                        'latency_ms' => 50 + $h * 8,
                        'fps' => 25 - $h * .2,
                        'last_frame_at' => now()->subMinutes($h * 5),
                        'cpu_usage' => 30 + $h,
                        'gpu_usage' => 25 + $h,
                        'memory_usage' => 45 + $h,
                        'message' => $camera->status === 'offline' ? 'Heartbeat unavailable' : null,
                        'recorded_at' => now()->subMinutes($h * 5),
                        'created_at' => now()->subMinutes($h * 5),
                    ]);
                }

                if ($z === 1 && $c === 1) {
                    $event = FireEvent::firstOrCreate(
                        ['event_uuid' => (string) Str::uuid()],
                        ['factory_id' => $factory->id, 'camera_id' => $camera->id, 'zone_id' => $zone->id, 'type' => 'fire', 'severity' => 'critical', 'status' => 'detected', 'confidence' => .94, 'detected_at' => now()->subMinutes(20)],
                    );
                    $alert = Alert::firstOrCreate(
                        ['fire_event_id' => $event->id, 'type' => 'fire'],
                        ['factory_id' => $factory->id, 'severity' => 'critical', 'status' => 'sent', 'message' => 'Critical fire detection'],
                    );
                    Notification::firstOrCreate(
                        ['factory_id' => $factory->id, 'fire_event_id' => $event->id, 'channel' => 'email', 'recipient' => 'security@firewatch.local'],
                        ['subject' => 'Fire alert', 'message' => $alert->message, 'status' => 'sent', 'sent_at' => now()],
                    );
                }
            }
        }

        AlertRecipient::firstOrCreate(
            ['factory_id' => $factory->id, 'name' => 'Security team'],
            ['user_id' => $users['security_agent']->id, 'email' => 'security@firewatch.local', 'phone' => '+212600000000', 'channels' => ['email', 'sms'], 'is_active' => true, 'severity_min' => 'high'],
        );

        ApiClient::firstOrCreate(
            ['factory_id' => $factory->id, 'name' => 'Edge Agent'],
            ['client_id' => (string) Str::uuid(), 'secret_hash' => hash('sha256', Str::random(64)), 'status' => 'active', 'allowed_ips' => ['127.0.0.1'], 'scopes' => ['camera:heartbeat', 'event:create']],
        );
    }
}
