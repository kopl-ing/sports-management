<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\SportsManagement\Controllers\PublicMatchController;

Route::get('/{token}', PublicMatchController::class)->where('token', '[A-Za-z0-9]{40}')->name('match');
