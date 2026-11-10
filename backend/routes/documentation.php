<?php

use App\Docs\Controllers\DocumentationController;
use Illuminate\Support\Facades\Route;

// Loaded from routes/api.php OUTSIDE the verified group (already under the /api prefix). Public API docs.
Route::get('/documentation', [DocumentationController::class, 'ui'])->name('documentation');
Route::get('/documentation/openapi.json', [DocumentationController::class, 'spec'])->name('documentation.spec');
