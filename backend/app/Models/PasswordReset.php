<?php

namespace App\Models;

use App\Support\EmailAddress;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * Stored lowercase like `users.email`, so a reset asked for as "Ali@Example.com" is found as "ali@example.com".
     *
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => $value === null ? null : EmailAddress::normalize($value));
    }
}
