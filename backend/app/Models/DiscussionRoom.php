<?php

namespace App\Models;

use Database\Factories\DiscussionRoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A group chat about a film. `is_locked` is set by staff only (it is not mass-assignable): while it is true nobody
 * can post a message, though the room stays readable and can still be joined.
 */
class DiscussionRoom extends Model
{
    /** @use HasFactory<DiscussionRoomFactory> */
    use HasFactory;

    protected $fillable = ['tmdb_id', 'media_type', 'name', 'created_by', 'spoilers', 'is_private'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['tmdb_id' => 'integer', 'spoilers' => 'boolean', 'is_private' => 'boolean', 'is_locked' => 'boolean', 'created_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'room_id');
    }

    /** The creator and everyone who has joined; a message is kept for 24 hours, and after that until all of them have read it. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_members', 'room_id', 'user_id');
    }

    /** People invited to this private room who have not joined or declined yet (`pivot->invited_by`). */
    public function invitees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_invites', 'room_id', 'user_id')->withPivot('invited_by');
    }

    /** The newest message still stored. */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'room_id')->latestOfMany();
    }
}
