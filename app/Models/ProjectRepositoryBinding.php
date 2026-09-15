<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRepositoryBinding extends Model
{
    use HasFactory;

    protected $table = 'project_repository_bindings';

    protected $primaryKey = 'id';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'provider',
        'binding_type',
        'remote_owner',
        'remote_repo',
        'remote_url',
        'local_path',
        'default_branch',
        'is_active',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the project that owns the repository binding.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
