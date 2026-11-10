<?php

use App\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
// The signed email-change confirm link lives in routes/email_change.php, outside that group.
Route::get('home', [UserController::class, 'home']);

Route::prefix('me')->controller(UserController::class)->group(function () {
    Route::get('/', 'showMe');
    Route::patch('/', 'updateMe');
    Route::delete('/', 'destroyMe');
    Route::patch('preferences', 'updatePreferences');
    Route::put('password', 'updatePassword')->middleware('throttle:6,1');
    Route::put('email', 'requestEmailChange')->middleware('throttle:me-email-change');
    Route::post('email/resend', 'resendEmailChange')->middleware('throttle:me-email-change');
    Route::delete('email', 'cancelEmailChange');
    Route::post('avatar', 'uploadAvatar');
    Route::delete('avatar', 'deleteAvatar');
    Route::delete('social/{provider}', 'disconnectSocial')->where('provider', 'google|github|discord');
    Route::post('follow-requests/{user:username}/accept', 'acceptFollowRequest');
    Route::delete('follow-requests/{user:username}', 'declineFollowRequest');
});

Route::get('users', [UserController::class, 'index']);
Route::prefix('users/{user:username}')->controller(UserController::class)->group(function () {
    Route::get('/', 'show');
    Route::get('followers', 'followers');
    Route::get('following', 'following');
    Route::post('follow', 'follow');
    Route::delete('follow', 'unfollow');
});
