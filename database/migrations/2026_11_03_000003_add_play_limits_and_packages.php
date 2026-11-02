<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playlist_zone_items', fn (Blueprint $t) => $t->unsignedInteger('max_plays')->default(0));
        Schema::create('device_item_counters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('device_id');
            $t->unsignedBigInteger('playlist_zone_item_id');
            $t->uuid('counter_epoch');
            $t->unsignedBigInteger('completed_count')->default(0);
            $t->timestamps();
            $t->unique(['device_id', 'playlist_zone_item_id'], 'device_item_counter_unique');
        });
        Schema::create('device_playback_packages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->unsignedBigInteger('device_id');
            $t->char('revision', 64);
            $t->json('grants');
            $t->timestamp('created_at');
            $t->unique(['device_id', 'revision'], 'device_package_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_playback_packages');
        Schema::dropIfExists('device_item_counters');
        Schema::table('playlist_zone_items', fn (Blueprint $t) => $t->dropColumn('max_plays'));
    }
};
