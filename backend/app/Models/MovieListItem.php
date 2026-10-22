<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieListItem extends Model
{
    protected $fillable = ['movie_list_id', 'tmdb_id', 'added_at'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['tmdb_id' => 'integer', 'added_at' => 'datetime'];
    }

    public function movieList(): BelongsTo
    {
        return $this->belongsTo(MovieList::class);
    }
}
