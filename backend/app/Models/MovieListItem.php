<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovieListItem extends Model
{
    protected $fillable = ['movie_list_id', 'tmdb_id', 'media_type', 'position', 'note', 'added_at'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['tmdb_id' => 'integer', 'position' => 'integer', 'added_at' => 'datetime'];
    }

    public function movieList(): BelongsTo
    {
        return $this->belongsTo(MovieList::class);
    }
}
