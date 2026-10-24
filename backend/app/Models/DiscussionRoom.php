<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
