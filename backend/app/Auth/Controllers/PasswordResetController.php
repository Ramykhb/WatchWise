<?php

namespace App\Auth\Controllers;

use App\Auth\Exceptions\InvalidResetToken;
use App\Auth\InterfaceServices\PasswordResetServiceInterface;
use App\Auth\Requests\ForgotPasswordRequest;
use App\Auth\Requests\ResetPasswordRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetServiceInterface $passwordResets) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResets->sendResetLink($request->toDto());

        return response()->json(['message' => 'If that email is registered, a password reset link has been sent.']);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->passwordResets->reset($request->toDto());
        } catch (InvalidResetToken) {
            return response()->json(['message' => 'Invalid or expired reset token.'], 422);
        }

        return response()->json(['message' => 'Password has been reset.']);
    }
}
