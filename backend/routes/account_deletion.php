<?php

use App\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php OUTSIDE the verified group (already under the /api prefix). The links mailed by
// DELETE /api/me (accounts whose password proves nothing) open in whatever browser holds that inbox, so they
// need no session: the signature proves they came from the email.
Route::get('me/delete/confirm/{id}/{hash}', [UserController::class, 'confirmAccountDeletion'])
    ->middleware(['signed', 'throttle:me-delete-confirm'])
    ->whereNumber('id')
    ->name('me.delete.confirm');
Route::get('me/delete/cancel/{id}/{hash}', [UserController::class, 'cancelAccountDeletionFromLink'])
    ->middleware(['signed', 'throttle:me-delete-confirm'])
    ->whereNumber('id')
    ->name('me.delete.cancel');
