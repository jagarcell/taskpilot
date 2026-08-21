<?php

use App\Enums\AgentRunStatus;
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
        Schema::create('agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('model')->nullable();
            $table->string('provider')->nullable();
            $table->string('status')->default(AgentRunStatus::PENDING->value);
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('token_usage')->nullable();
            $table->timestamps();

            $table->index('issue_id');
            $table->index('agent_id');
            $table->index('status');
            $table->index(['issue_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_runs');
    }
};
