<?php

use App\Http\Controllers\MatchingPartnerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LiveChatProfilesController;
use App\Http\Middleware\UserAuthenticationMiddleware;
use App\Http\Controllers\MatchingUserController;
use App\Http\Controllers\WebhookController;


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

    // List negotiations (deal-done list)
    Route::get('/matching/negotiations', [MatchingUserController::class, 'negotiationsList']);

    // Mark negotiation as a deal done (status = 4) if both sides already matched
    Route::post('/matching/negotiations', [MatchingUserController::class, 'markDealDone']);
});

Route::controller(WebhookController::class)->group(function (): void {
    Route::post('webhook/csv-list-trigger', 'handleCsvListTriggerWebhook')->name('webhook.csv-list-trigger');
    Route::post('webhook/business-approvement', 'businessApprovement')->name('webhook.business-approvement');
});
