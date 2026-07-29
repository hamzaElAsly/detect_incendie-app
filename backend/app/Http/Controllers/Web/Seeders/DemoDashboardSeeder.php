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
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
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
        $accounts = ['super_admin' => 'superadmin@firewatch.demo', 'platform_admin' => 'platform@firewatch.demo', 'factory_owner' => 'owner@firewatch.demo', 'factory_manager' => 'manager@firewatch.demo', 'safety_officer' => 'safety@firewatch.demo', 'security_agent' => 'security@firewatch.demo', 'maintenance_agent' => 'maintenance@firewatch.demo', 'viewer' => 'viewer@firewatch.demo'];
        $users = collect($accounts)->mapWithKeys(function ($email, $role) use ($password) {
            $u = User::updateOrCreate(['email' => $email], ['name' => str($role)->replace('_', ' ')->title(), 'password' => $password, 'is_active' => true, 'is_super_admin' => $role === 'super_admin', 'email_verified_at' => now()]);

            return [$role => $u];
        });
        $atlas = Factory::updateOrCreate(['slug' => 'atlas-casablanca'], ['name' => 'Atlas Manufacturing Casablanca', 'reference' => 'FAC-ATLAS', 'city' => 'Casablanca', 'country' => 'Maroc', 'timezone' => 'Africa/Casablanca', 'status' => 'active', 'is_active' => true, 'owner_id' => $users['factory_owner']->id]);
        $nord = Factory::updateOrCreate(['slug' => 'nord-tanger'], ['name' => 'Nord Industries Tanger', 'reference' => 'FAC-NORD', 'city' => 'Tanger', 'country' => 'Maroc', 'timezone' => 'Africa/Casablanca', 'status' => 'active', 'is_active' => true, 'owner_id' => $users['factory_owner']->id]);
        $users->except(['super_admin', 'platform_admin'])->each(function ($u, $role) use ($atlas, $nord) {
            foreach ([$atlas, $nord] as $i => $factory) {
                if ($i && in_array($role, ['factory_manager', 'safety_officer', 'security_agent', 'maintenance_agent'], true)) {
                    continue;
                }$factory->users()->syncWithoutDetaching([$u->id => ['role' => $role, 'status' => 'active', 'joined_at' => now()]]);
                $r = Role::where('name', $role)->firstOrFail();
                $exists = $u->roles()->whereKey($r->id)->wherePivot('factory_id', $factory->id)->exists();
                if (! $exists) {
                    $u->roles()->attach($r, ['factory_id' => $factory->id]);
                }
            }
        });
        $platform = Role::where('name', 'platform_admin')->firstOrFail();
        if (! $users['platform_admin']->roles()->whereKey($platform->id)->exists()) {
            $users['platform_admin']->roles()->attach($platform, ['factory_id' => null]);
        }
        $plan = Plan::updateOrCreate(['code' => 'professional'], ['name' => 'Professional', 'max_cameras' => 50, 'max_users' => 25, 'features' => ['Détection feu et fumée', 'Alertes multicanal', 'Rapports avancés'], 'is_active' => true]);
        foreach ([$atlas, $nord] as $factory) {
            Subscription::updateOrCreate(['factory_id' => $factory->id], ['plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subMonth(), 'ends_at' => now()->addYear()]);
            for ($z = 1; $z <= 2; $z++) {
                $zone = Zone::withTrashed()->firstOrCreate(['factory_id' => $factory->id, 'reference' => 'Z-'.$z], ['name' => 'Zone '.$z, 'risk_level' => $z === 1 ? 'high' : 'medium', 'floor' => 'Niveau '.$z, 'status' => 'active']);
                for ($c = 1; $c <= 2; $c++) {
                    $camera = Camera::withTrashed()->firstOrCreate(['factory_id' => $factory->id, 'reference' => 'CAM-'.$z.$c], ['zone_id' => $zone->id, 'name' => 'Caméra '.$z.$c, 'brand' => 'Hikvision', 'model' => 'DS-2CD', 'status' => $c === 1 ? 'online' : 'offline', 'is_active' => true, 'fire_threshold' => .65, 'smoke_threshold' => .6]);
                    for ($h = 0; $h < 8; $h++) {
                        CameraHealthLog::forceCreate(['factory_id' => $factory->id, 'camera_id' => $camera->id, 'status' => $camera->status, 'latency_ms' => 50 + $h * 8, 'fps' => 25 - $h * .2, 'last_frame_at' => now()->subMinutes($h * 5), 'cpu_usage' => 30 + $h, 'gpu_usage' => 25 + $h, 'memory_usage' => 45 + $h, 'message' => $camera->status === 'offline' ? 'Heartbeat indisponible' : null, 'recorded_at' => now()->subMinutes($h * 5), 'created_at' => now()->subMinutes($h * 5)]);
                    }if ($z === 1 && $c === 1) {
                        $event = FireEvent::firstOrCreate(['event_uuid' => (string) Str::uuid()], ['factory_id' => $factory->id, 'camera_id' => $camera->id, 'zone_id' => $zone->id, 'type' => 'fire', 'severity' => 'critical', 'status' => 'detected', 'confidence' => .94, 'detected_at' => now()->subMinutes(20)]);
                        $alert = Alert::firstOrCreate(['fire_event_id' => $event->id, 'type' => 'fire'], ['factory_id' => $factory->id, 'severity' => 'critical', 'status' => 'sent', 'message' => 'Détection incendie critique']);
                        Notification::firstOrCreate(['factory_id' => $factory->id, 'fire_event_id' => $event->id, 'channel' => 'email', 'recipient' => 'security@firewatch.demo'], ['subject' => 'Alerte incendie', 'message' => $alert->message, 'status' => 'sent', 'sent_at' => now()]);
                    }
                }
            }AlertRecipient::firstOrCreate(['factory_id' => $factory->id, 'name' => 'Équipe sécurité'], ['user_id' => $users['security_agent']->id, 'email' => 'security@firewatch.demo', 'phone' => '+212600000000', 'channels' => ['email', 'sms'], 'is_active' => true, 'severity_min' => 'high']);
            ApiClient::firstOrCreate(['factory_id' => $factory->id, 'name' => 'Détection IA'], ['client_id' => (string) Str::uuid(), 'secret_hash' => hash('sha256', Str::random(64)), 'status' => 'active', 'allowed_ips' => ['127.0.0.1']]);
        }
    }
}
