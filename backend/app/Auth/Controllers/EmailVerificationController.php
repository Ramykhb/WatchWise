<?php

namespace App\Auth\Controllers;

use App\Auth\DTOs\Requests\VerifyEmailData;
use App\Auth\FormRequests\ResendVerificationRequest;
use App\Auth\InterfaceServices\EmailVerificationServiceInterface;
use App\Support\AccountLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController
{
    public function __construct(private EmailVerificationServiceInterface $verification) {}

    /**
     * GET /api/auth/verify-email/{id}/{hash} — the signed link in the verification email. Changes nothing: it only
     * opens the public page whose click POSTs the same signed URL, so a mail scanner or link preview that prefetches
     * it verifies nothing.
     */
    public function open(Request $request): RedirectResponse
    {
        return redirect(AccountLink::VerifyEmail->confirmationPage($request));
    }

    /** POST /api/auth/verify-email/{id}/{hash} — the click on that page (signed, no login): verifies the address. */
    public function verify(int $id, string $hash): JsonResponse
    {
        return response()->json(['outcome' => $this->verification->verify(new VerifyEmailData($id, $hash))]);
    }

    public function resend(ResendVerificationRequest $request): JsonResponse
    {
        $this->verification->resend($request->toDto());

        return response()->json(['message' => 'If the account exists and is unverified, a verification email has been sent.']);
    }
}
