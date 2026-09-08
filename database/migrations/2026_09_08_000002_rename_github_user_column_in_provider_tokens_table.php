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
        if (Schema::hasColumn('provider_tokens', 'github_user') && ! Schema::hasColumn('provider_tokens', 'provider_user')) {
            Schema::table('provider_tokens', function (Blueprint $table) {
                $table->renameColumn('github_user', 'provider_user');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('provider_tokens', 'provider_user') && ! Schema::hasColumn('provider_tokens', 'github_user')) {
            Schema::table('provider_tokens', function (Blueprint $table) {
                $table->renameColumn('provider_user', 'github_user');
            });
        }
    }
};
