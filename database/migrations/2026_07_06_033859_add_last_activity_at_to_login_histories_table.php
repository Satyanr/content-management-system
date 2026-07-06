<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_histories', function (Blueprint $table) {
            $table->timestamp('last_activity_at')
                ->nullable()
                ->after('login_at');

            $table->index('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::table('login_histories', function (Blueprint $table) {
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn('last_activity_at');
        });
    }
};