<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kopling\SportsManagement\Controllers\MatchesController;
use Kopling\SportsManagement\Controllers\StaffController;
use Kopling\SportsManagement\Controllers\TeamMembersController;
use Kopling\SportsManagement\Controllers\TeamsController;
use Kopling\SportsManagement\Controllers\TrackingController;

// The Portal already gates on access-sports-management; every controller action also checks Team::isStaffedBy().
Route::get('/', [TeamsController::class, 'index'])->name('teams.index');
Route::get('/{team}', [TeamsController::class, 'show'])->name('teams.show');
Route::get('/{team}/matches/{teamMatch}', [MatchesController::class, 'show'])->name('matches.show');
Route::get('/{team}/matches/{teamMatch}/track', [TrackingController::class, 'show'])->name('matches.track');

Route::post('/invitations/{invitation}/accept', [StaffController::class, 'accept'])->name('invitations.accept');
Route::post('/invitations/{invitation}/decline', [StaffController::class, 'decline'])->name('invitations.decline');
// Leaving needs no permission; removing someone else is checked in the controller.
Route::post('/{team}/staff/{person}/remove', [StaffController::class, 'remove'])->name('teams.staff.destroy');

Route::middleware('can:kopling-sports-management::manage-teams')->group(function () {
    Route::post('/', [TeamsController::class, 'store'])->middleware('throttle:10,60,sm-teams')->name('teams.store');
    Route::post('/{team}', [TeamsController::class, 'update'])->name('teams.update');
    Route::post('/{team}/delete', [TeamsController::class, 'destroy'])->name('teams.destroy');

    Route::post('/{team}/invitations', [StaffController::class, 'invite'])->middleware('throttle:20,60,sm-invitations')->name('teams.invitations.store');
    Route::post('/{team}/invitations/{invitation}/delete', [StaffController::class, 'revoke'])->name('teams.invitations.destroy');
    Route::post('/{team}/staff/{person}/owner', [StaffController::class, 'makeOwner'])->name('teams.staff.owner');

    Route::post('/{team}/members', [TeamMembersController::class, 'store'])->middleware('throttle:60,60,sm-members')->name('teams.members.store');
    Route::post('/{team}/members/{teamMember}', [TeamMembersController::class, 'update'])->name('teams.members.update');
    Route::post('/{team}/members/{teamMember}/delete', [TeamMembersController::class, 'destroy'])->name('teams.members.destroy');
});

Route::middleware('can:kopling-sports-management::manage-matches')->group(function () {
    Route::post('/{team}/matches', [MatchesController::class, 'store'])->name('matches.store');
    Route::post('/{team}/matches/{teamMatch}', [MatchesController::class, 'update'])->name('matches.update');
    Route::post('/{team}/matches/{teamMatch}/delete', [MatchesController::class, 'destroy'])->name('matches.destroy');
    Route::post('/{team}/matches/{teamMatch}/availability', [MatchesController::class, 'updateAvailability'])->name('matches.availability');
    Route::post('/{team}/matches/{teamMatch}/lineup', [MatchesController::class, 'moveInLineup'])->name('matches.lineup');
});

Route::middleware('can:kopling-sports-management::track-matches')->prefix('/{team}/matches/{teamMatch}/track')->group(function () {
    Route::post('/periods/start', [TrackingController::class, 'startPeriod'])->name('matches.periods.start');
    Route::post('/periods', [TrackingController::class, 'storePeriod'])->name('matches.periods.store');
    Route::post('/periods/{period}', [TrackingController::class, 'updatePeriod'])->name('matches.periods.update');
    Route::post('/periods/{period}/end', [TrackingController::class, 'endPeriod'])->name('matches.periods.end');
    Route::post('/periods/{period}/delete', [TrackingController::class, 'destroyPeriod'])->name('matches.periods.destroy');

    Route::post('/substitutions', [TrackingController::class, 'storeSubstitution'])->name('matches.substitutions.store');
    Route::post('/field', [TrackingController::class, 'moveOnField'])->name('matches.field');
    Route::post('/undo', [TrackingController::class, 'undo'])->name('matches.undo');
    Route::post('/substitutions/{substitution}/delete', [TrackingController::class, 'destroySubstitution'])->name('matches.substitutions.destroy');

    Route::post('/goals', [TrackingController::class, 'storeGoal'])->name('matches.goals.store');
    Route::post('/goals/{goal}/delete', [TrackingController::class, 'destroyGoal'])->name('matches.goals.destroy');
});
