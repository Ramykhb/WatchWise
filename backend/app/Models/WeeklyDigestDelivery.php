<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyDigestDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'sent_at' => 'datetime', 'skipped_at' => 'datetime'];
    }
}
