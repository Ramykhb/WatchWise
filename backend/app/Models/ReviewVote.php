<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A member marking another member's review as helpful (one vote per member and review). */
class ReviewVote extends Model
{
    protected $fillable = ['review_id', 'user_id'];

    public const UPDATED_AT = null;

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
