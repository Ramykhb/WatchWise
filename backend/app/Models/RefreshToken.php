<?php

namespace App\Models;

use Database\Factories\RefreshTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    /** @use HasFactory<RefreshTokenFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'token_hash', 'family_id', 'family_started_at', 'expires_at', 'revoked_at', 'replaced_by', 'grace_used_at', 'user_agent', 'ip',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['family_started_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'grace_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
