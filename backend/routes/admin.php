<?php

use App\Admin\Controllers\AdminController;
use App\Admin\Controllers\AdminGenreController;
use App\Admin\Controllers\AdminListController;
use App\Admin\Controllers\AdminRatingController;
use App\Admin\Controllers\AdminReportController;
use App\Admin\Controllers\AdminReviewToolsController;
use App\Admin\Controllers\AdminSettingController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix),
// so staff need a verified email too. The policy gates repeat the role checks, and the `admin` limiter (see
// AdminModuleServiceProvider) throttles every route here per signed-in user.
Route::prefix('admin')->middleware('throttle:admin')->group(function () {
    Route::controller(AdminController::class)->group(function () {
        Route::middleware('role:admin')->group(function () {
            Route::delete('users/{user}', 'destroyUser')->whereNumber('user');

            Route::get('movies', 'movies');
            Route::patch('movies/{tmdbId}', 'updateMovie')->whereNumber('tmdbId');

            Route::get('audit-log', 'auditLog');
        });

        // Moderators get read-only stats and users, may suspend or reinstate plain members and moderate reviews.
        Route::middleware('role:admin,moderator')->group(function () {
            Route::get('stats', 'stats');

            Route::get('users', 'users');
            Route::patch('users/{user}', 'updateUser')->whereNumber('user');

            Route::get('reviews', 'reviews');
            Route::delete('reviews/{review}', 'destroyReview')->whereNumber('review');
        });
    });

    // Moderators and admins look at a review in full, flag its spoilers and decide on one review or several.
    Route::middleware('role:admin,moderator')->controller(AdminReviewToolsController::class)->group(function () {
        Route::post('reviews/bulk', 'bulk');
        Route::get('reviews/{review}', 'show')->whereNumber('review');
        Route::patch('reviews/{review}', 'update')->whereNumber('review');
    });

    // Moderators and admins triage the content reports.
    Route::middleware('role:admin,moderator')->controller(AdminReportController::class)->group(function () {
        Route::get('reports', 'index');
        Route::get('reports/{report}', 'show')->whereNumber('report');
        Route::patch('reports/{report}', 'update')->whereNumber('report');
    });

    // Moderators and admins look through star ratings, spot rating bursts and remove ratings.
    Route::middleware('role:admin,moderator')->controller(AdminRatingController::class)->group(function () {
        Route::get('ratings', 'index');
        Route::get('ratings/films/{tmdbId}', 'film')->whereNumber('tmdbId');
        Route::get('ratings/bursts', 'bursts');
        Route::delete('ratings/{rating}', 'destroy')->whereNumber('rating');
        Route::delete('users/{user}/ratings', 'purge')->whereNumber('user');
    });

    // Moderators may look at the genres but only admins change them.
    Route::controller(AdminGenreController::class)->group(function () {
        Route::middleware('role:admin')->group(function () {
            Route::patch('genres/{type}/{tmdbId}', 'update')->whereIn('type', ['movie', 'tv'])->whereNumber('tmdbId');
            Route::delete('genres/{type}/{tmdbId}', 'destroy')->whereIn('type', ['movie', 'tv'])->whereNumber('tmdbId');
        });

        Route::middleware('role:admin,moderator')->group(function () {
            Route::get('genres', 'index');
        });
    });

    // Moderators and admins read every member's lists, make one private and remove a film from one; only admins delete one.
    Route::controller(AdminListController::class)->group(function () {
        Route::middleware('role:admin')->group(function () {
            Route::delete('lists/{list}', 'destroy')->whereNumber('list');
        });

        Route::middleware('role:admin,moderator')->group(function () {
            Route::get('lists', 'index');
            Route::get('lists/{list}', 'show')->whereNumber('list');
            Route::patch('lists/{list}', 'update')->whereNumber('list');
            Route::delete('lists/{list}/items/{tmdbId}', 'removeItem')->whereNumber('list')->whereNumber('tmdbId');
        });
    });

    // Settings change how the whole app behaves, so they stay with admins.
    Route::middleware('role:admin')->group(function () {
        Route::get('settings', [AdminSettingController::class, 'index']);
        Route::patch('settings', [AdminSettingController::class, 'update']);
    });
});
