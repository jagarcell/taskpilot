<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('github_tokens') && ! Schema::hasTable('provider_tokens')) {
            Schema::rename('github_tokens', 'provider_tokens');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('provider_tokens') && ! Schema::hasTable('github_tokens')) {
            Schema::rename('provider_tokens', 'github_tokens');
        }
    }
};
