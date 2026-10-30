<?php

namespace App\Auth\Controllers;

use App\Auth\FormRequests\ForgotPasswordRequest;
use App\Auth\FormRequests\ResetPasswordRequest;
use App\Auth\InterfaceServices\PasswordResetServiceInterface;
use Illuminate\Http\JsonResponse;

class PasswordResetController
{
    public function __construct(private PasswordResetServiceInterface $passwordResets) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResets->sendResetLink($request->toDto());

        return response()->json(['message' => 'If that email is registered, a password reset link has been sent.']);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResets->reset($request->toDto());

        return response()->json(['message' => 'Password has been reset.']);
    }
}
