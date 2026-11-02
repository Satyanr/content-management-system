<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            $t->foreignId('primary_playlist_id')->nullable()->constrained('playlists')->nullOnDelete();
            $t->uuid('playlist_command_id')->nullable();
            $t->timestamp('playlist_command_at')->nullable();
            $t->unsignedBigInteger('reported_playlist_id')->nullable();
            $t->string('reported_command_id', 64)->nullable();
            $t->string('reported_revision', 64)->nullable();
            $t->timestamp('playback_reported_at')->nullable();
        });
        Schema::create('device_playlist_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('device_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('playlist_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('source', 32);
            $t->string('command_id', 64);
            $t->timestamp('created_at');
            $t->index(['company_id', 'device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_playlist_changes');
        Schema::table('devices', function (Blueprint $t) {
            $t->dropConstrainedForeignId('primary_playlist_id');
            $t->dropColumn(['playlist_command_id', 'playlist_command_at', 'reported_playlist_id', 'reported_command_id', 'reported_revision', 'playback_reported_at']);
        });
    }
};
