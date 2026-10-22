<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionRoom extends Model
{
    protected $fillable = ['tmdb_id', 'name'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['tmdb_id' => 'integer', 'created_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'room_id');
    }
}
