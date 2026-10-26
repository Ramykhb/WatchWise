<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DiscussionRoom extends Model
{
    protected $fillable = ['tmdb_id', 'name', 'created_by', 'spoilers'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['tmdb_id' => 'integer', 'spoilers' => 'boolean', 'created_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'room_id');
    }

    /** The creator and everyone who has posted; a message is kept until all of them have read it. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_members', 'room_id', 'user_id');
    }

    /** The newest message still stored (someone has yet to read it). */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'room_id')->latestOfMany();
    }
}
