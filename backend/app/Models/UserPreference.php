<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreference extends Model
{
    protected $fillable = ['user_id', 'favorite_genres', 'favorite_actor_ids', 'favorite_director_ids', 'settings'];

    public const UPDATED_AT = 'updated_at';

    public const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'favorite_genres' => 'array',
            'favorite_actor_ids' => 'array',
            'favorite_director_ids' => 'array',
            'settings' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
