<?php

use App\Admin\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php (already under the /api prefix). The policy gates repeat the role checks.
Route::prefix('admin')->controller(AdminController::class)->group(function () {
    Route::middleware(['auth.jwt', 'role:admin'])->group(function () {
        Route::delete('users/{user}', 'destroyUser')->whereNumber('user');

        Route::get('movies', 'movies');
        Route::patch('movies/{tmdbId}', 'updateMovie')->whereNumber('tmdbId');
    });

    // Moderators get read-only stats and users, may suspend or reinstate plain members, and moderate reviews.
    Route::middleware(['auth.jwt', 'role:admin,moderator'])->group(function () {
        Route::get('stats', 'stats');

        Route::get('users', 'users');
        Route::patch('users/{user}', 'updateUser')->whereNumber('user');

        Route::get('reviews', 'reviews');
        Route::patch('reviews/{review}', 'moderateReview')->whereNumber('review');
        Route::delete('reviews/{review}', 'destroyReview')->whereNumber('review');
    });
});
