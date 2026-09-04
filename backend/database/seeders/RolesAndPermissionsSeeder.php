<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $permissions = [
                'factories.view',
                'factories.update',
                'users.view',
                'users.invite',
                'users.update',
                'users.remove',
                'zones.view',
                'zones.create',
                'zones.update',
                'zones.delete',
                'cameras.view',
                'cameras.create',
                'cameras.update',
                'cameras.delete',
                'cameras.test',
                'cameras.credentials.manage',
                'events.view',
                'events.create',
                'events.confirm',
                'events.resolve',
                'events.mark_false_alarm',
                'events.delete',
                'alerts.view',
                'alerts.manage',
                'notifications.view',
                'notifications.manage',
                'recipients.view',
                'recipients.manage',
                'api_clients.view',
                'api_clients.manage',
                'settings.view',
                'settings.update',
                'reports.view',
                'audit_logs.view',
                'roles.view',
                'roles.manage',
            ];

            foreach ($permissions as $permission) {
                Permission::firstOrCreate(['name' => $permission]);
            }

            Permission::whereNotIn('name', $permissions)->delete();

            $this->mergeRole('super_admin', 'system_admin', 'System Admin', true);
            $this->mergeRole('platform_admin', 'system_admin', 'System Admin', true);
            $this->mergeRole('factory_owner', 'factory_admin', 'Factory Admin', false);

            $roles = [
                'system_admin' => ['label' => 'System Admin', 'is_platform' => true],
                'factory_admin' => ['label' => 'Factory Admin', 'is_platform' => false],
                'factory_manager' => ['label' => 'Factory Manager', 'is_platform' => false],
                'safety_officer' => ['label' => 'Safety Officer', 'is_platform' => false],
                'security_agent' => ['label' => 'Security Agent', 'is_platform' => false],
                'maintenance_agent' => ['label' => 'Maintenance Agent', 'is_platform' => false],
                'viewer' => ['label' => 'Viewer', 'is_platform' => false],
            ];

            $all = $permissions;
            $operational = array_values(array_diff($permissions, ['roles.view', 'roles.manage']));
            $map = [
                'system_admin' => $all,
                'factory_admin' => array_values(array_diff($operational, ['audit_logs.view'])),
                'factory_manager' => [
                    'factories.view',
                    'users.view',
                    'zones.view',
                    'zones.create',
                    'zones.update',
                    'zones.delete',
                    'cameras.view',
                    'cameras.create',
                    'cameras.update',
                    'cameras.delete',
                    'cameras.test',
                    'events.view',
                    'events.confirm',
                    'events.resolve',
                    'events.mark_false_alarm',
                    'alerts.view',
                    'alerts.manage',
                    'notifications.view',
                    'recipients.view',
                    'reports.view',
                ],
                'safety_officer' => [
                    'factories.view',
                    'users.view',
                    'zones.view',
                    'cameras.view',
                    'events.view',
                    'events.confirm',
                    'events.resolve',
                    'events.mark_false_alarm',
                    'alerts.view',
                    'alerts.manage',
                    'notifications.view',
                    'recipients.view',
                    'reports.view',
                ],
                'security_agent' => [
                    'factories.view',
                    'zones.view',
                    'cameras.view',
                    'events.view',
                    'events.confirm',
                    'alerts.view',
                    'alerts.manage',
                    'notifications.view',
                    'recipients.view',
                ],
                'maintenance_agent' => [
                    'factories.view',
                    'zones.view',
                    'cameras.view',
                    'cameras.update',
                    'cameras.test',
                    'cameras.credentials.manage',
                    'events.view',
                ],
                'viewer' => [
                    'factories.view',
                    'zones.view',
                    'cameras.view',
                    'events.view',
                    'alerts.view',
                    'notifications.view',
                    'reports.view',
                ],
            ];

            foreach ($roles as $name => $attributes) {
                $role = Role::updateOrCreate(['name' => $name], $attributes);
                $role->permissions()->sync(Permission::whereIn('name', $map[$name])->pluck('id'));
            }
        });
    }

    private function mergeRole(string $from, string $to, string $label, bool $isPlatform): void
    {
        $source = Role::where('name', $from)->first();
        if (! $source) {
            Role::firstOrCreate(['name' => $to], ['label' => $label, 'is_platform' => $isPlatform]);

            return;
        }

        $target = Role::firstOrCreate(['name' => $to], ['label' => $label, 'is_platform' => $isPlatform]);
        DB::table('permission_role')->where('role_id', $source->id)->orderBy('permission_id')->get()->each(function ($row) use ($target): void {
            DB::table('permission_role')->updateOrInsert(['permission_id' => $row->permission_id, 'role_id' => $target->id], []);
        });
        DB::table('role_user')->where('role_id', $source->id)->orderBy('user_id')->get()->each(function ($row) use ($target, $isPlatform): void {
            DB::table('role_user')->updateOrInsert(
                ['role_id' => $target->id, 'user_id' => $row->user_id, 'factory_id' => $isPlatform ? null : $row->factory_id],
                [],
            );
        });
        DB::table('factory_user')->where('role', $from)->update(['role' => $to]);
        $source->delete();
        $target->update(['label' => $label, 'is_platform' => $isPlatform]);
    }
}
