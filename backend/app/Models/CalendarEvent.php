<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    protected $fillable = ['user_id', 'tmdb_id', 'movie_night_id', 'provider', 'external_event_id', 'event_type', 'scheduled_at'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'scheduled_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movieNight(): BelongsTo
    {
        return $this->belongsTo(MovieNight::class);
    }
}
