<?php

use App\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
// The signed email-change confirm link lives in routes/email_change.php, outside that group.
Route::get('home', [UserController::class, 'home']);

Route::prefix('me')->controller(UserController::class)->group(function () {
    Route::get('/', 'showMe');
    Route::patch('/', 'updateMe')->middleware('throttle:user-profile-update');
    Route::delete('/', 'destroyMe')->middleware('throttle:me-account-deletion');
    Route::patch('preferences', 'updatePreferences')->middleware('throttle:user-profile-update');
    Route::put('password', 'updatePassword')->middleware('throttle:6,1');
    Route::put('password/set', 'setPassword')->middleware('throttle:6,1')->name('me.password.set');
    Route::put('email', 'requestEmailChange')->middleware('throttle:me-email-change');
    Route::post('email/resend', 'resendEmailChange')->middleware('throttle:me-email-change');
    Route::delete('email', 'cancelEmailChange');
    Route::post('avatar', 'uploadAvatar')->middleware('throttle:user-avatar');
    Route::delete('avatar', 'deleteAvatar')->middleware('throttle:user-avatar');
    Route::delete('social/{provider}', 'disconnectSocial')->where('provider', 'google|github|discord');
    Route::post('follow-requests/{user:username}/accept', 'acceptFollowRequest')->middleware('throttle:user-follows');
    Route::delete('follow-requests/{user:username}', 'declineFollowRequest')->middleware('throttle:user-follows');
});

Route::get('users', [UserController::class, 'index'])->middleware('throttle:user-search');
Route::prefix('users/{user:username}')->controller(UserController::class)->group(function () {
    Route::get('/', 'show');
    Route::get('followers', 'followers');
    Route::get('following', 'following');
    Route::post('report', 'report')->middleware('throttle:report');
    Route::post('follow', 'follow')->middleware('throttle:user-follows');
    Route::delete('follow', 'unfollow')->middleware('throttle:user-follows');
});
