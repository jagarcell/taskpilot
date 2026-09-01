<?php

namespace App\Models;

use Database\Factories\ProjectGitHubRepositoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectGitHubRepository extends Model
{
    /** @use HasFactory<ProjectGitHubRepositoryFactory> */
    use HasFactory;

    protected $table = 'project_github_repositories';

    protected $primaryKey = 'id';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'github_owner',
        'github_repo',
        'default_branch',
        'repository_url',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'project_id' => 'integer',
        ];
    }

    /**
     * Get the project that owns the repository connection.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
