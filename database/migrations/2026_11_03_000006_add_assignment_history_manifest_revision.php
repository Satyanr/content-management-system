<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_playlist_changes', fn (Blueprint $t) => $t->char('manifest_revision', 64)->nullable());
    }

    public function down(): void
    {
        Schema::table('device_playlist_changes', fn (Blueprint $t) => $t->dropColumn('manifest_revision'));
    }
};
