<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    /**
     * `access_token` is deliberately not fillable: provider tokens are no longer stored, and the column (emptied
     * by a migration) only remains until it is dropped.
     */
    protected $fillable = ['user_id', 'provider', 'provider_id'];

    protected $hidden = ['access_token'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
