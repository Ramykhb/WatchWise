<?php

use App\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php OUTSIDE the verified group (already under the /api prefix). The links mailed by
// DELETE /api/me (accounts whose password proves nothing) open in whatever browser holds that inbox, so they
// need no session: the signature proves they came from the email. A GET on the confirmation link only redirects to
// the public frontend page, whose click POSTs the same signed URL: mail scanners and link previews that prefetch
// it delete nothing. Cancelling is the safe direction, so it still acts on GET.
Route::get('me/delete/confirm/{id}/{hash}', [UserController::class, 'openAccountDeletionConfirmation'])
    ->middleware(['signed', 'throttle:me-delete-confirm'])
    ->whereNumber('id')
    ->name('me.delete.confirm');
Route::post('me/delete/confirm/{id}/{hash}', [UserController::class, 'confirmAccountDeletion'])
    ->middleware(['signed', 'throttle:me-delete-confirm'])
    ->whereNumber('id')
    ->name('me.delete.confirm.submit');
Route::get('me/delete/cancel/{id}/{hash}', [UserController::class, 'cancelAccountDeletionFromLink'])
    ->middleware(['signed', 'throttle:me-delete-confirm'])
    ->whereNumber('id')
    ->name('me.delete.cancel');
