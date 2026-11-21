<?php

namespace App\Models;

use App\Movie\DTOs\Requests\UpdateLocalMovieData;
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
        'override_fields', 'genre_ids', 'is_hidden', 'is_featured', 'synced_at', 'edited_at',
    ];

    /** Legacy edited rows predate field tracking and retain their complete local content. */
    public function contentOverrideFields(): array
    {
        return $this->override_fields ?? ($this->edited_at === null ? [] : UpdateLocalMovieData::CONTENT_FIELDS);
    }

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'runtime' => 'integer',
            'release_date' => 'date',
            'genre_ids' => 'array',
            'override_fields' => 'array',
            'is_hidden' => 'boolean',
            'is_featured' => 'boolean',
            'synced_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }
}
