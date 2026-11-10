<?php

use App\User\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php inside its `auth.jwt` + `verified.email` group (already under the /api prefix).
// DELETE /api/me itself lives in routes/user.php; its signed links are in routes/account_deletion.php.
Route::delete('me/delete-request', [UserController::class, 'cancelAccountDeletion']);
