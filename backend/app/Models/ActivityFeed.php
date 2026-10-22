<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityFeed extends Model
{
    protected $fillable = ['user_id', 'type', 'tmdb_id', 'reference_id', 'reference_type', 'is_public'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'reference_id' => 'integer',
            'is_public' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
