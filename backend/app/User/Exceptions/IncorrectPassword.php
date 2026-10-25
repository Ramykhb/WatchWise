<?php

namespace App\User\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** The password the user typed to confirm a sensitive change is wrong. Rendered as a 422 on that field. */
class IncorrectPassword extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    public static function forCurrentPassword(): self
    {
        return new self('current_password', 'Your current password is incorrect.');
    }

    public static function forAccountDeletion(): self
    {
        return new self('password', 'That password is incorrect.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => [$this->field => [$this->getMessage()]],
        ], 422);
    }
}
