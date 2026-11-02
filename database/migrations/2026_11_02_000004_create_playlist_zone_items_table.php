<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlist_zone_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_zone_id')->constrained('playlist_zones')->cascadeOnDelete();
            // Prevent silently breaking a Playlist when a media asset is deleted.
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            // NULL = use source video duration; image/PDF uses an explicit duration.
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->index(['playlist_zone_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_zone_items');
    }
};
