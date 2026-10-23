<?php

use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php (already under the /api prefix).
Route::prefix('auth')->group(function () {
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:auth-verify-email'])
        ->whereNumber('id')
        ->name('auth.verify-email');
    Route::post('resend-verification', [EmailVerificationController::class, 'resend'])->middleware('throttle:auth-resend-verification');

    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:auth-forgot-password');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:auth-reset-password');

    Route::prefix('social/{provider}')->where(['provider' => 'google|github'])->group(function () {
        Route::get('/', [SocialAuthController::class, 'redirect']);
        Route::get('callback', [SocialAuthController::class, 'callback']);
    });
});
