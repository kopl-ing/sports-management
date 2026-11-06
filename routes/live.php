<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\SportsManagement\Controllers\PublicMatchController;
use Kopling\SportsManagement\Controllers\PublicTasksController;

Route::get('/{token}', PublicMatchController::class)->where('token', '[A-Za-z0-9]{40}')->name('match');

Route::where(['token' => '[A-Za-z0-9]{40}'])->group(function () {
    Route::get('/team/{token}/tasks', [PublicTasksController::class, 'picker'])->name('tasks.team');
    Route::get('/team/{token}/tasks/{member}', [PublicTasksController::class, 'teamPlayer'])->whereUuid('member')->name('tasks.team.player');
    Route::get('/player/{token}/tasks', [PublicTasksController::class, 'player'])->name('tasks.player');

    Route::middleware('throttle:60,10,sm-task-links')->group(function () {
        Route::post('/team/{token}/tasks/{member}', [PublicTasksController::class, 'teamEnlist'])->whereUuid('member')->name('tasks.team.enlist');
        Route::post('/team/{token}/tasks/{member}/leave/{enlistment}', [PublicTasksController::class, 'teamLeave'])->whereUuid('member')->name('tasks.team.leave');
        Route::post('/player/{token}/tasks', [PublicTasksController::class, 'playerEnlist'])->name('tasks.player.enlist');
        Route::post('/player/{token}/tasks/leave/{enlistment}', [PublicTasksController::class, 'playerLeave'])->name('tasks.player.leave');
    });
});
