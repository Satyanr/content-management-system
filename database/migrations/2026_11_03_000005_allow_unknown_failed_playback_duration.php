<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playback_events', fn (Blueprint $t) => $t->unsignedBigInteger('expected_duration_ms')->nullable()->change());
    }

    public function down(): void
    { /* Preserve unknown historical durations; no destructive coercion. */
    }
};
