<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlist_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_id')->constrained('playlists')->cascadeOnDelete();
            $table->string('name', 100)->default('Page 1');
            $table->unsignedInteger('sort_order')->default(1);
            $table->unsignedInteger('duration_seconds')->default(15);
            $table->string('background_color', 7)->default('#000000');
            $table->timestamps();
            $table->index(['playlist_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_pages');
    }
};
