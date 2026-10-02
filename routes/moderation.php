<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\SportsManagement\Controllers\ModerationController;

// Inside the Moderation portal's own group, which already gates every route on its `moderate` permission.
Route::get('/sports-management', [ModerationController::class, 'index'])->name('kopling-sports-management.teams');
