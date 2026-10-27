<?php

use App\Admin\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php (already under the /api prefix). Admins only; the policy gates repeat the check.
Route::prefix('admin')->middleware(['auth.jwt', 'role:admin'])->controller(AdminController::class)->group(function () {
    Route::get('stats', 'stats');

    Route::get('users', 'users');
    Route::patch('users/{user}', 'updateUser')->whereNumber('user');
    Route::delete('users/{user}', 'destroyUser')->whereNumber('user');

    Route::get('reviews', 'reviews');
    Route::patch('reviews/{review}', 'moderateReview')->whereNumber('review');
    Route::delete('reviews/{review}', 'destroyReview')->whereNumber('review');

    Route::get('movies', 'movies');
    Route::patch('movies/{tmdbId}', 'updateMovie')->whereNumber('tmdbId');
});
