<?php

namespace App\Models;

use Database\Factories\GitHubTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GitHubToken extends Model
{
    /** @use HasFactory<GitHubTokenFactory> */
    use HasFactory;

    protected $table = 'github_tokens';

    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'provider',
        'access_token',
        'refresh_token',
        'token_type',
        'scope',
        'github_user',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
