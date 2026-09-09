<?php

namespace App\Models;

use Database\Factories\ProviderOAuthCredentialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderOAuthCredential extends Model
{
    /** @use HasFactory<ProviderOAuthCredentialFactory> */
    use HasFactory;

    protected $table = 'provider_oauth_credentials';

    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'provider',
        'client_id',
        'client_secret',
        'redirect_uri',
        'scope',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
