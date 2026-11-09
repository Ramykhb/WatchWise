<?php

use App\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php OUTSIDE the verified group (already under the /api prefix). The link mailed to a
// new address opens in whatever browser holds that inbox, so it needs no session (the signature proves it
// came from the email).
Route::get('me/email/confirm/{id}/{hash}', [UserController::class, 'confirmEmailChange'])
    ->middleware(['signed', 'throttle:me-email-confirm'])
    ->whereNumber('id')
    ->name('me.email.confirm');
