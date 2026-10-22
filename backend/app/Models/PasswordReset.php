<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    protected $fillable = ['email', 'token'];

    public const UPDATED_AT = null;

    protected $table = 'password_resets';

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
