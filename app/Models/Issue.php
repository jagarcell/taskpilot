<?php

namespace App\Models;

use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueType;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    protected $table = 'issues';

    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'reporter_id',
        'assignee_id',
        'issue_key',
        'title',
        'description',
        'type',
        'status',
        'priority',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IssueType::class,
            'status' => IssueStatus::class,
            'priority' => IssuePriority::class,
            'reporter_id' => 'integer',
            'assignee_id' => 'integer',
            'project_id' => 'integer',
        ];
    }

    /**
     * Get the project that owns the issue.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who reported the issue.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * Get the user assigned to the issue.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the labels attached to the issue.
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'issue_labels')->withTimestamps();
    }

    /**
     * Get the comments attached to the issue.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
