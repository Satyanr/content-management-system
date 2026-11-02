<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            $t->unsignedInteger('pending_playback_events')->default(0);
            $t->timestamp('events_received_at')->nullable();
        });
        Schema::create('playback_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('device_id');
            $t->uuid('event_id');
            $t->uuid('playback_session_id');
            foreach (['playlist_id', 'page_id', 'zone_id', 'playlist_zone_item_id', 'media_asset_id'] as $column) {
                $t->unsignedBigInteger($column);
            }
            $t->unsignedBigInteger('schedule_id')->nullable();
            $t->uuid('counter_epoch');
            $t->char('revision', 64);
            $t->char('payload_hash', 64);
            $t->dateTime('start_time_utc', 3);
            $t->dateTime('end_time_utc', 3);
            $t->unsignedBigInteger('actual_played_ms');
            $t->unsignedBigInteger('expected_duration_ms');
            $t->string('status', 16);
            $t->string('reason', 80);
            $t->json('names');
            $t->dateTime('received_at', 3);
            $t->unique(['device_id', 'event_id'], 'device_event_unique');
            $t->unique(['device_id', 'playback_session_id'], 'device_session_unique');
            $t->index(['company_id', 'start_time_utc'], 'playback_company_time');
            $t->index(['company_id', 'device_id', 'playlist_zone_item_id'], 'playback_item_report');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playback_events');
        Schema::table('devices', fn (Blueprint $t) => $t->dropColumn(['pending_playback_events', 'events_received_at']));
    }
};
