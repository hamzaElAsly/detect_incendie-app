<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factories', function (Blueprint $table): void {
            if (! Schema::hasColumn('factories', 'legal_name')) {
                $table->string('legal_name')->nullable();
            }
            if (! Schema::hasColumn('factories', 'installation_identifier')) {
                $table->string('installation_identifier', 150)->nullable()->index();
            }
            if (! Schema::hasColumn('factories', 'emergency_contact')) {
                $table->string('emergency_contact')->nullable();
            }
            if (! Schema::hasColumn('factories', 'logo_path')) {
                $table->string('logo_path')->nullable();
            }
            if (! Schema::hasColumn('factories', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('factories', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('factories', 'internal_notes')) {
                $table->text('internal_notes')->nullable();
            }
        });

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
                DB::table('permissions')->updateOrInsert(['name' => $permission], ['updated_at' => now(), 'created_at' => now()]);
            }

            $this->mergeRole('super_admin', 'system_admin', 'System Admin', true);
            $this->mergeRole('platform_admin', 'system_admin', 'System Admin', true);
            $this->mergeRole('factory_owner', 'factory_admin', 'Factory Admin', false);

            DB::table('factory_user')->where('role', 'factory_owner')->update(['role' => 'factory_admin']);

            $stalePermissions = [
                'factories.create',
                'factories.delete',
                'plans.view',
                'plans.manage',
                'subscriptions.view',
                'subscriptions.manage',
                'billing.view',
                'billing.manage',
                'tenants.view',
                'tenants.manage',
            ];

            DB::table('permissions')->whereIn('name', $stalePermissions)->delete();
        });
    }

    public function down(): void
    {
        Schema::table('factories', function (Blueprint $table): void {
            foreach (['legal_name', 'installation_identifier', 'emergency_contact', 'logo_path', 'latitude', 'longitude', 'internal_notes'] as $column) {
                if (Schema::hasColumn('factories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function mergeRole(string $from, string $to, string $label, bool $isPlatform): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => $to],
            ['label' => $label, 'is_platform' => $isPlatform, 'updated_at' => now(), 'created_at' => now()],
        );

        $source = DB::table('roles')->where('name', $from)->first();
        $target = DB::table('roles')->where('name', $to)->first();

        if (! $source || ! $target || $source->id === $target->id) {
            return;
        }

        DB::table('permission_role')->where('role_id', $source->id)->orderBy('permission_id')->get()->each(function ($row) use ($target): void {
            DB::table('permission_role')->updateOrInsert(['permission_id' => $row->permission_id, 'role_id' => $target->id], []);
        });

        DB::table('role_user')->where('role_id', $source->id)->orderBy('user_id')->get()->each(function ($row) use ($target, $isPlatform): void {
            DB::table('role_user')->updateOrInsert(
                ['role_id' => $target->id, 'user_id' => $row->user_id, 'factory_id' => $isPlatform ? null : $row->factory_id],
                [],
            );
        });

        DB::table('roles')->where('id', $source->id)->delete();
    }
};
