<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieNightCandidate extends Model
{
    protected $fillable = ['movie_night_id', 'tmdb_id', 'added_by'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['tmdb_id' => 'integer'];
    }

    public function movieNight(): BelongsTo
    {
        return $this->belongsTo(MovieNight::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
