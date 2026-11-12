<?php

namespace App\Models;

use Database\Factories\AdminUserNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal note staff keep about a member. It is never shown to the member and never emailed.
 */
class AdminUserNote extends Model
{
    /** @use HasFactory<AdminUserNoteFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** The longest note staff may write. */
    public const BODY_MAX = 1000;

    protected $fillable = ['user_id', 'author_id', 'body'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'author_id' => 'integer', 'created_at' => 'datetime'];
    }

    /** The member the note is about. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The staff member who wrote it, or null once their account is deleted. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
