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
        DB::transaction(function () {
            $permissions = ['factories.view', 'factories.create', 'factories.update', 'factories.delete', 'users.view', 'users.invite', 'users.update', 'users.remove', 'zones.view', 'zones.create', 'zones.update', 'zones.delete', 'cameras.view', 'cameras.create', 'cameras.update', 'cameras.delete', 'cameras.test', 'cameras.credentials.manage', 'events.view', 'events.create', 'events.confirm', 'events.resolve', 'events.mark_false_alarm', 'events.delete', 'alerts.view', 'alerts.manage', 'notifications.view', 'notifications.manage', 'recipients.view', 'recipients.manage', 'api_clients.view', 'api_clients.manage', 'plans.view', 'plans.manage', 'subscriptions.view', 'subscriptions.manage', 'settings.view', 'settings.update', 'reports.view', 'audit_logs.view'];
            foreach ($permissions as $p) {
                Permission::firstOrCreate(['name' => $p]);
            }
            $roles = ['super_admin' => 'Super Admin', 'platform_admin' => 'Platform Admin', 'factory_owner' => 'Factory Owner', 'factory_manager' => 'Factory Manager', 'safety_officer' => 'Safety Officer', 'security_agent' => 'Security Agent', 'maintenance_agent' => 'Maintenance Agent', 'viewer' => 'Viewer'];
            $billing = ['plans.view', 'plans.manage', 'subscriptions.view', 'subscriptions.manage', 'api_clients.view', 'api_clients.manage'];
            $map = ['super_admin' => $permissions, 'platform_admin' => array_values(array_diff($permissions, ['factories.delete'])), 'factory_owner' => array_values(array_diff($permissions, array_merge($billing, ['factories.create', 'factories.delete', 'audit_logs.view']))), 'factory_manager' => array_values(array_diff($permissions, array_merge($billing, ['factories.create', 'factories.delete', 'audit_logs.view', 'users.invite', 'users.update', 'users.remove', 'recipients.manage']))), 'safety_officer' => ['factories.view', 'users.view', 'zones.view', 'cameras.view', 'events.view', 'events.confirm', 'events.resolve', 'events.mark_false_alarm', 'alerts.view', 'alerts.manage', 'notifications.view', 'recipients.view', 'reports.view'], 'security_agent' => ['factories.view', 'zones.view', 'cameras.view', 'events.view', 'events.confirm', 'alerts.view', 'alerts.manage', 'notifications.view', 'recipients.view'], 'maintenance_agent' => ['factories.view', 'zones.view', 'cameras.view', 'cameras.update', 'cameras.test', 'cameras.credentials.manage', 'events.view'], 'viewer' => ['factories.view', 'zones.view', 'cameras.view', 'events.view', 'alerts.view', 'notifications.view', 'reports.view']];
            foreach ($roles as $name => $label) {
                $role = Role::firstOrCreate(['name' => $name], ['label' => $label, 'is_platform' => in_array($name, ['super_admin', 'platform_admin'], true)]);
                $role->permissions()->sync(Permission::whereIn('name', $map[$name])->pluck('id'));
            }
            $rolePermissions = collect(['roles.view', 'roles.manage'])->map(fn (string $name) => Permission::firstOrCreate(['name' => $name]));
            Role::whereIn('name', ['super_admin', 'platform_admin'])->get()->each(
                fn (Role $role) => $role->permissions()->syncWithoutDetaching($rolePermissions->pluck('id')),
            );
        });
    }
}
