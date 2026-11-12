<?php

namespace App\Models;

use Database\Factories\GenreOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff's local layer over one TMDB genre: a display name, a hidden flag and a position. TMDB's genre list is
 * never changed; this row is applied on top of it. `type` is `movie` or `tv`, since the two lists can share ids.
 */
class GenreOverride extends Model
{
    /** @use HasFactory<GenreOverrideFactory> */
    use HasFactory;

    protected $fillable = ['type', 'tmdb_id', 'name', 'is_hidden', 'position', 'updated_by'];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'is_hidden' => 'boolean',
            'position' => 'integer',
        ];
    }
}
