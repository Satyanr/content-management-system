<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->string('group')->default('general');
            $table->string('key');
            $table->longText('value')->nullable();
            $table->string('type')->default('text');
            $table->boolean('is_public')->default(false);

            $table->timestamps();

            $table->index(['company_id', 'group']);
            $table->index(['company_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
