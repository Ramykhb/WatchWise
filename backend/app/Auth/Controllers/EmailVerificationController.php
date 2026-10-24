<?php

namespace App\Auth\Controllers;

use App\Auth\DTOs\Requests\VerifyEmailData;
use App\Auth\FormRequests\ResendVerificationRequest;
use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class EmailVerificationController extends Controller
{
    public function __construct(private EmailVerificationServiceInterface $verification) {}

    /** Signed link target (GET). Marks the email verified, then redirects to the frontend. */
    public function verify(int $id, string $hash): RedirectResponse
    {
        $front = rtrim(config('services.frontend_url'), '/');
        $status = $this->verification->verify(new VerifyEmailData($id, $hash)) ? 'success' : 'invalid';

        return redirect("{$front}/verify-email?status={$status}");
    }

    public function resend(ResendVerificationRequest $request): JsonResponse
    {
        $this->verification->resend($request->toDto());

        return response()->json(['message' => 'If the account exists and is unverified, a verification email has been sent.']);
    }
}
