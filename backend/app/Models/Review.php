<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['user_id', 'tmdb_id', 'content', 'rating', 'sentiment', 'is_approved'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_approved' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
