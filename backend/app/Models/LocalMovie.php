<?php

namespace App\Models;

use Database\Factories\LocalMovieFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocalMovie extends Model
{
    /** @use HasFactory<LocalMovieFactory> */
    use HasFactory;

    protected $table = 'movies';

    protected $fillable = [
        'tmdb_id', 'title', 'overview', 'poster_path', 'release_date', 'runtime', 'director',
        'genre_ids', 'is_hidden', 'is_featured', 'synced_at', 'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'runtime' => 'integer',
            'release_date' => 'date',
            'genre_ids' => 'array',
            'is_hidden' => 'boolean',
            'is_featured' => 'boolean',
            'synced_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }
}
