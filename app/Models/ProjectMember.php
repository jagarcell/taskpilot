<?php

namespace App\Models;

use App\Enums\ProjectMemberRole;
use Database\Factories\ProjectMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    /** @use HasFactory<ProjectMemberFactory> */
    use HasFactory;

    protected $table = 'project_members';

    protected $primaryKey = 'id';

    protected $fillable = [
        'project_id',
        'user_id',
        'role',
    ];

    protected $casts = [
        'role' => ProjectMemberRole::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
