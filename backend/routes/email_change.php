<?php

use App\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php OUTSIDE the verified group (already under the /api prefix). The links mailed for an
// email change open in whatever browser holds that inbox, so they need no session (the signature proves they came
// from the email). A GET on a link that changes something only redirects to the public frontend page, whose click
// POSTs the same signed URL: mail scanners and link previews that prefetch it change nothing.

// To the new address: confirm it.
Route::get('me/email/confirm/{id}/{hash}', [UserController::class, 'openEmailChangeConfirmation'])
    ->middleware(['signed', 'throttle:me-email-confirm'])
    ->whereNumber('id')
    ->name('me.email.confirm');
Route::post('me/email/confirm/{id}/{hash}', [UserController::class, 'confirmEmailChange'])
    ->middleware(['signed', 'throttle:me-email-confirm'])
    ->whereNumber('id')
    ->name('me.email.confirm.submit');

// To the current address: approve (accounts whose password proves nothing) or cancel. Cancelling is the safe
// direction, so it still acts on GET.
Route::get('me/email/authorize/{id}/{hash}', [UserController::class, 'openEmailChangeAuthorization'])
    ->middleware(['signed', 'throttle:me-email-confirm'])
    ->whereNumber('id')
    ->name('me.email.authorize');
Route::post('me/email/authorize/{id}/{hash}', [UserController::class, 'authorizeEmailChange'])
    ->middleware(['signed', 'throttle:me-email-confirm'])
    ->whereNumber('id')
    ->name('me.email.authorize.submit');
Route::get('me/email/cancel/{id}/{hash}', [UserController::class, 'cancelEmailChangeFromLink'])
    ->middleware(['signed', 'throttle:me-email-confirm'])
    ->whereNumber('id')
    ->name('me.email.cancel');
