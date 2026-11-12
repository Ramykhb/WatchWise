<?php

namespace App\Models;

use App\Report\Support\ReportStatus;
use App\Report\Support\ReportTargetType;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member reporting a review, room message, movie list, discussion room or member profile (one report per
 * member and target). The target is polymorphic with no foreign key, so a report outlives its content. It stays
 * open until staff resolve or dismiss it.
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $fillable = ['reporter_id', 'target_type', 'target_id', 'reason', 'details', 'target_label', 'target_excerpt', 'status', 'resolved_at', 'resolved_by', 'resolution_note'];

    protected function casts(): array
    {
        return [
            'target_type' => ReportTargetType::class,
            'target_id' => 'integer',
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Reports nobody has decided yet.
     *
     * @param  Builder<Report>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', ReportStatus::Open);
    }

    /** The member who filed the report. */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** The staff member who closed it, or null while it is open, after a system closure or once their account is deleted. */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
