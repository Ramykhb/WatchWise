<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieNightInvite extends Model
{
    protected $fillable = ['movie_night_id', 'user_id', 'status'];

    public const UPDATED_AT = null;

    public function movieNight(): BelongsTo
    {
        return $this->belongsTo(MovieNight::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
