<?php

namespace App\Models;

use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    protected $table = 'agents';

    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'provider',
        'model',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }
}
