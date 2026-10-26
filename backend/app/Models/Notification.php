<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = ['user_id', 'type', 'data', 'read_at'];

    public const UPDATED_AT = null;

    /**
     * A room message the user still has to read (`data`: room_id, message_id). `read_at` is set once they have
     * seen it, and the message is deleted, with these rows, when every recipient has.
     */
    public const ROOM_MESSAGE = 'room_message';

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
