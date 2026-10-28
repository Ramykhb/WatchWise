<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    protected $fillable = ['user_id', 'tmdb_id', 'media_type', 'content', 'rating', 'sentiment', 'has_spoilers', 'is_approved', 'moderation_labels'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_approved' => 'boolean', 'has_spoilers' => 'boolean', 'moderation_labels' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Reviews with text; star ratings are rows without any.
     *
     * @param  Builder<Review>  $query
     */
    public function scopeWithText(Builder $query): void
    {
        $query->whereNotNull('content')->where('content', '!=', '');
    }

    /** Members who marked this review as helpful. */
    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }
}
