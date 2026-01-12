<?php

use App\Http\Controllers\Api\MatchingPartnerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LiveChatProfilesController;
use App\Http\Middleware\UserAuthenticationMiddleware;

Route::name('users.')
    ->controller(LiveChatProfilesController::class)
    ->group(function (): void {
        // GET /api/users/policy-status
        Route::get('user-status', 'checkUserFistLoginAndAgreePolicy')
            ->name('policy-status');

        // POST /api/users/policy-agreement
        Route::post('policy-agreement', 'userAgreement')
            ->name('policy-agreement');
    });
Route::middleware(UserAuthenticationMiddleware::class)->group(function (): void {
    Route::name('users.')
        ->controller(MatchingPartnerController::class)
        ->group(function (): void {
            // GET /api/users/me
            Route::get('matching-partners', 'index')
                ->name('matching-partners');
        });
});
