<?php

namespace App\Models;

use App\Report\Support\ReportTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovieList extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'is_public'];

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Reports members filed against this list. */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'target_id')->where('target_type', ReportTargetType::MovieList);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MovieListItem::class)->orderBy('position')->orderBy('id');
    }
}
