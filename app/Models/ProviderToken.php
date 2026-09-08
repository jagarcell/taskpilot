<?php

namespace App\Models;

use Database\Factories\ProviderTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderToken extends Model
{
    /** @use HasFactory<ProviderTokenFactory> */
    use HasFactory;

    protected $table = 'provider_tokens';

    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
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
}
