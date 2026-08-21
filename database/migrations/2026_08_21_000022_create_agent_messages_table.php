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
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_run_id')->constrained('agent_runs')->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('agent_run_id');
            $table->index(['agent_run_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};
