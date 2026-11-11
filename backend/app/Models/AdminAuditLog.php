<?php

namespace App\Models;

use App\Admin\Support\AdminAction;
use App\Admin\Support\AdminSubjectType;
use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One thing a staff member did, kept for good. Rows are only ever inserted (by the Admin module's audit
 * repository, which sets every column explicitly); nothing is mass-assignable, and saving a changed row
 * or deleting one through the model throws.
 */
class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log rows cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit log rows cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'action' => AdminAction::class,
            'subject_type' => AdminSubjectType::class,
            'subject_id' => 'integer',
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** The staff member, or null once their account is deleted. */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
