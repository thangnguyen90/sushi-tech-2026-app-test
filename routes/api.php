<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LiveChatProfilesController;

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

