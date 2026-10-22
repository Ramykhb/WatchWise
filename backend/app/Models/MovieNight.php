<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovieNight extends Model
{
    protected $fillable = ['organizer_id', 'title', 'scheduled_at', 'location', 'meeting_link', 'status', 'winning_tmdb_id'];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'winning_tmdb_id' => 'integer',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(MovieNightInvite::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(MovieNightCandidate::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(MovieNightVote::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }
}
