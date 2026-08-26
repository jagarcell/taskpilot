<?php

namespace App\Models;

use Database\Factories\WorkflowDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    /** @use HasFactory<WorkflowDefinitionFactory> */
    use HasFactory;

    protected $table = 'workflow_definitions';

    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'steps',
        'config',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'config' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }
}
