<?php

namespace App\Models;

use App\Enums\AgentRunStatus;
use Database\Factories\AgentRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentRun extends Model
{
    /** @use HasFactory<AgentRunFactory> */
    use HasFactory;

    protected $table = 'agent_runs';

    protected $primaryKey = 'id';

    protected $fillable = [
        'issue_id',
        'agent_id',
        'user_id',
        'model',
        'provider',
        'status',
        'input',
        'output',
        'error',
        'started_at',
        'finished_at',
        'token_usage',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgentRunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'error' => 'array',
            'token_usage' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class);
    }
}
