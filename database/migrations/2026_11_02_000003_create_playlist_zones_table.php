<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playlist_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('playlist_page_id')->constrained('playlist_pages')->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->integer('z_index')->default(0);
            $table->boolean('keep_aspect_ratio')->default(true);
            $table->string('fit_mode', 12)->default('contain');
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->index(['playlist_page_id', 'z_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_zones');
    }
};
