<?php

namespace App\Models;

use Database\Factories\RepositoryTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryToken extends Model
{
    /** @use HasFactory<RepositoryTokenFactory> */
    use HasFactory;

    protected $table = 'repository_tokens';

    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'project_id',
        'provider',
        'access_token',
        'refresh_token',
        'token_type',
        'scope',
        'provider_user',
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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
