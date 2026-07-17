<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('is_super_admin')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('is_super_admin');
        });

        Schema::create('factories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('factory_user', function (Blueprint $table) {
            $table->foreignUlid('factory_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 50)->default('member');
            $table->timestamps();
            $table->primary(['factory_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('api_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->char('token', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
        Schema::dropIfExists('factory_user');
        Schema::dropIfExists('factories');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_active', 'is_super_admin', 'last_login_at']));
    }
};
