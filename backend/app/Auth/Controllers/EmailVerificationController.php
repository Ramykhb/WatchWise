<?php

namespace App\Auth\Controllers;

use App\Auth\Requests\ResendVerificationRequest;
use App\Auth\Services\EmailVerificationService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /** Signed link target (GET). Marks the email verified, then redirects to the frontend. */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $front = rtrim(config('services.frontend_url'), '/');
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->email), $hash)) {
            return redirect("{$front}/verify-email?status=invalid");
        }

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect("{$front}/verify-email?status=success");
    }

    public function resend(ResendVerificationRequest $request, EmailVerificationService $service): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user && ! $user->email_verified_at) {
            $service->send($user);
        }

        return response()->json(['message' => 'If the account exists and is unverified, a verification email has been sent.']);
    }
}
