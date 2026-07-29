<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table): void {
            $table->json('scopes')->nullable()->after('allowed_ips');
            $table->timestamp('expires_at')->nullable()->after('scopes')->index();
            $table->ipAddress('last_used_ip')->nullable()->after('last_used_at');
            $table->timestamp('revoked_at')->nullable()->after('expires_at');
        });

        Schema::table('cameras', function (Blueprint $table): void {
            $table->timestamp('last_heartbeat_at')->nullable()->after('last_seen_at')->index();
            $table->timestamp('last_frame_at')->nullable()->after('last_heartbeat_at');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('fps', 8, 2)->nullable();
            $table->string('resolution', 30)->nullable();
            $table->string('edge_agent_version', 100)->nullable();
            $table->string('model_version', 100)->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->text('last_error_message')->nullable();
            $table->foreignUlid('last_api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('timezone', 100)->nullable()->after('avatar_path');
            $table->json('preferences')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['avatar_path', 'timezone', 'preferences']));
        Schema::table('cameras', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_api_client_id');
            $table->dropColumn(['last_heartbeat_at', 'last_frame_at', 'latency_ms', 'fps', 'resolution', 'edge_agent_version', 'model_version', 'last_error_code', 'last_error_message']);
        });
        Schema::table('api_clients', fn (Blueprint $table) => $table->dropColumn(['scopes', 'expires_at', 'last_used_ip', 'revoked_at']));
    }
};
