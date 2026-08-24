<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->foreignId('media_folder_id')
                ->nullable()
                ->after('company_id')
                ->constrained('media_folders')
                ->nullOnDelete();

            $table->index(['company_id', 'media_folder_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'media_folder_id']);
            $table->dropConstrainedForeignId('media_folder_id');
        });
    }
};