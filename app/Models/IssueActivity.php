<?php

namespace App\Models;

use Database\Factories\IssueActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueActivity extends Model
{
    /** @use HasFactory<IssueActivityFactory> */
    use HasFactory;

    protected $table = 'issue_activities';

    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'issue_id',
        'user_id',
        'type',
        'message',
        'context',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'issue_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /**
     * Get the issue this activity belongs to.
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * Get the user who triggered the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
