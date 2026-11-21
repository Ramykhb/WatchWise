<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovieNightCalendarDelivery extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['revision' => 'integer', 'cancelled' => 'boolean', 'sent_at' => 'datetime', 'skipped_at' => 'datetime'];
    }
}
