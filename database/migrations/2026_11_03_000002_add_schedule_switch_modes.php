<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $t) {
            $t->string('playback_mode', 16)->default('legacy');
            $t->string('target_type', 16)->default('all');
            $t->json('target_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', fn (Blueprint $t) => $t->dropColumn(['playback_mode', 'target_type', 'target_ids']));
    }
};
