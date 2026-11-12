<?php

use App\Admin\Controllers\AdminController;
use App\Admin\Controllers\AdminSettingController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix),
// so staff need a verified email too. The policy gates repeat the role checks, and the `admin` limiter (see
// AdminModuleServiceProvider) throttles every route here per signed-in user.
Route::prefix('admin')->middleware('throttle:admin')->controller(AdminController::class)->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::delete('users/{user}', 'destroyUser')->whereNumber('user');

        Route::get('movies', 'movies');
        Route::patch('movies/{tmdbId}', 'updateMovie')->whereNumber('tmdbId');

        Route::get('audit-log', 'auditLog');

        Route::get('settings', [AdminSettingController::class, 'index']);
        Route::patch('settings', [AdminSettingController::class, 'update']);
    });

    // Moderators get read-only stats and users, may suspend or reinstate plain members, and moderate reviews.
    Route::middleware('role:admin,moderator')->group(function () {
        Route::get('stats', 'stats');

        Route::get('users', 'users');
        Route::patch('users/{user}', 'updateUser')->whereNumber('user');

        Route::get('reviews', 'reviews');
        Route::patch('reviews/{review}', 'moderateReview')->whereNumber('review');
        Route::delete('reviews/{review}', 'destroyReview')->whereNumber('review');
    });
});
