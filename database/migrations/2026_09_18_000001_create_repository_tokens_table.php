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
        if (! Schema::hasTable('repository_tokens')) {
            Schema::create('repository_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->string('provider')->default('github');
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->string('token_type')->default('bearer');
                $table->string('scope')->nullable();
                $table->string('provider_user')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'project_id', 'provider']);
                $table->index('expires_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repository_tokens');
    }
};
