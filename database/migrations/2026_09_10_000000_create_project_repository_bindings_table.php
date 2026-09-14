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
        Schema::create('project_repository_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('provider')->default('github');
            $table->string('binding_type')->default('remote');
            $table->string('remote_owner')->nullable();
            $table->string('remote_repo')->nullable();
            $table->string('remote_url')->nullable();
            $table->string('local_path')->nullable();
            $table->string('default_branch')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique('project_id');
            $table->index(['provider', 'binding_type']);
            $table->index(['remote_owner', 'remote_repo']);
            $table->index('local_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_repository_bindings');
    }
};
