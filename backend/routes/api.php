<?php

use App\Auth\Controllers\AuthController;
use App\Health\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Thin entry point: each entity or module owns one routes file under routes/ and is required from the right
// group below. A new entity gets its own file here, not more routes in this one.

Route::get('/health', HealthController::class)->name('health');

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:auth-refresh');
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me'])->middleware('auth.jwt');
});

// Exempt from `verified.email` so an unverified member can verify, resend the link or reset the password.
require __DIR__.'/auth_flows.php';

// Every signed-in route outside /api/auth needs a verified email: guests get 401, unverified members 403
// (`code: email_unverified`). Add new signed-in routes as a bare routes file required inside this group
// (no `auth.jwt` wrapper in the file); RequireVerifiedEmailTest checks it.
Route::middleware(['auth.jwt', 'verified.email'])->group(function () {
    require __DIR__.'/user.php';
    require __DIR__.'/account_deletion_signed_in.php';
    require __DIR__.'/lists.php';
    require __DIR__.'/watchlist.php';
    require __DIR__.'/ratings.php';
    require __DIR__.'/reviews.php';
    require __DIR__.'/movie_nights.php';
    require __DIR__.'/rooms.php';
    require __DIR__.'/notifications.php';
    require __DIR__.'/chat.php';
    require __DIR__.'/recommendations.php';
    require __DIR__.'/admin.php';
});

// Public routes, outside the verified group. The signed email-change and account-deletion links need no
// session (the signature proves they came from the email).
require __DIR__.'/email_change.php';
require __DIR__.'/account_deletion.php';
require __DIR__.'/weather.php';
require __DIR__.'/setting.php';
require __DIR__.'/recommendations_public.php';
require __DIR__.'/cinemas.php';
require __DIR__.'/movies.php';
require __DIR__.'/documentation.php';
