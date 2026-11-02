<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('platform', 12)->default('other');
            $table->string('location', 200)->nullable();
            $table->string('status', 12)->default('pending');
            $table->unsignedInteger('screen_width')->nullable();
            $table->unsignedInteger('screen_height')->nullable();
            $table->string('orientation', 12)->default('landscape');
            $table->string('player_version', 50)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->json('heartbeat')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'id']);
            $table->index(['company_id', 'status', 'last_seen_at']);
        });
        Schema::create('device_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'id']);
        });
        Schema::create('device_group_device', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('device_group_id');
            $table->unique(['device_id', 'device_group_id']);
            $table->foreign(['company_id', 'device_id'])->references(['company_id', 'id'])->on('devices')->cascadeOnDelete();
            $table->foreign(['company_id', 'device_group_id'])->references(['company_id', 'id'])->on('device_groups')->cascadeOnDelete();
        });
        Schema::create('device_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();
            $table->index(['device_id', 'consumed_at', 'cancelled_at']);
        });
        Schema::create('device_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->char('token_identifier', 32)->unique();
            $table->char('token_hash', 64);
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_credentials');
        Schema::dropIfExists('device_pairing_codes');
        Schema::dropIfExists('device_group_device');
        Schema::dropIfExists('device_groups');
        Schema::dropIfExists('devices');
    }
};
