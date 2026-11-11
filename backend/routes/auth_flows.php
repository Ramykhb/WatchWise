<?php

use App\Auth\Controllers\EmailVerificationController;
use App\Auth\Controllers\PasswordResetController;
use App\Auth\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php (already under the /api prefix).
Route::prefix('auth')->group(function () {
    // The signed link in the verification email. A GET only redirects to the public frontend page, whose click POSTs
    // the same signed URL: mail scanners and link previews that prefetch it verify nothing.
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'open'])
        ->middleware(['signed', 'throttle:auth-verify-email'])
        ->whereNumber('id')
        ->name('auth.verify-email');
    Route::post('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:auth-verify-email'])
        ->whereNumber('id')
        ->name('auth.verify-email.submit');
    Route::post('resend-verification', [EmailVerificationController::class, 'resend'])->middleware('throttle:auth-resend-verification');

    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:auth-forgot-password');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:auth-reset-password');

    Route::prefix('social/{provider}')->where(['provider' => 'google|github|discord'])->group(function () {
        Route::get('/', [SocialAuthController::class, 'redirect'])->middleware('throttle:auth-social-redirect');
        Route::get('callback', [SocialAuthController::class, 'callback'])->middleware('throttle:auth-social-callback');
    });
});
