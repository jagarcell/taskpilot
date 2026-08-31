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
        Schema::create('project_github_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('github_owner');
            $table->string('github_repo');
            $table->string('default_branch')->default('main');
            $table->string('repository_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('project_id');
            $table->index(['github_owner', 'github_repo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_github_repositories');
    }
};
