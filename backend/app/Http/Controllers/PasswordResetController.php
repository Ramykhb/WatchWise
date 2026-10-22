<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Mail\ResetPasswordMail;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    private const EXPIRES_MINUTES = 60;

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $user = User::where('email', $email)->first();

        if ($user) {
            $plain = Str::random(64);
            PasswordReset::where('email', $email)->delete();
            $record = new PasswordReset(['email' => $email, 'token' => Hash::make($plain)]);
            $record->created_at = now();
            $record->save();

            $url = rtrim(config('services.frontend_url'), '/').'/reset-password?'
                .http_build_query(['token' => $plain, 'email' => $email]);

            Mail::to($email)->send(new ResetPasswordMail($user->name, $url, self::EXPIRES_MINUTES));
        }

        return response()->json(['message' => 'If that email is registered, a password reset link has been sent.']);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $invalid = response()->json(['message' => 'Invalid or expired reset token.'], 422);

        $record = PasswordReset::where('email', $data['email'])->latest('id')->first();

        if (! $record
            || ! $record->created_at
            || $record->created_at->lt(now()->subMinutes(self::EXPIRES_MINUTES))
            || ! Hash::check($data['token'], $record->token)) {
            return $invalid;
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return $invalid;
        }

        DB::transaction(function () use ($user, $data) {
            $user->password = $data['password']; // 'hashed' cast
            $user->save();

            PasswordReset::where('email', $data['email'])->delete();

            if (Schema::hasTable('refresh_tokens')) {
                $query = DB::table('refresh_tokens')->where('user_id', $user->id);
                Schema::hasColumn('refresh_tokens', 'revoked_at')
                    ? $query->update(['revoked_at' => now()])
                    : $query->delete();
            }
        });

        return response()->json(['message' => 'Password has been reset.']);
    }
}
