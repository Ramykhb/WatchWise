<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * The one rule set for a password a member chooses: registration and password reset (Auth), setting a first
 * password and changing it (User). Each form request adds only what is specific to it.
 */
final class NewPasswordRules
{
    /** bcrypt ignores every byte after the 72nd, so a longer password would only look stronger than it is. */
    public const MAX_BYTES = 72;

    /**
     * Required, a string without a NUL byte (bcrypt ignores everything after one), confirmed by
     * `password_confirmation`, at least 8 characters with letters and numbers, at most 72 bytes.
     *
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'not_regex:/\x00/',
            'confirmed',
            Password::min(8)->letters()->numbers(),
            new MaxBytes(self::MAX_BYTES),
        ];
    }
}
