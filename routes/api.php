<?php

use App\Http\Controllers\AppointmentCheckinController;
use App\Http\Controllers\ChatProfileContentController;
use App\Http\Controllers\FreeReceptionCheckinController;
use App\Http\Controllers\LiveChatProfilesController;
use App\Http\Controllers\MatchingPartnerController;
use App\Http\Controllers\MatchingUserController;
use App\Http\Controllers\ReceptionCheckinController;
use App\Http\Controllers\ReceptionCompleteCheckinController;
use App\Http\Controllers\ReceptionUserShowController;
use App\Http\Controllers\RoomAppointmentsController;
use App\Http\Controllers\RoomShowController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\UserAuthenticationMiddleware;
use Illuminate\Support\Facades\Route;

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

            Route::get('/matching/{profile_id}/details', 'show')
                ->name('matching-partners-details');

            Route::post('/matching/ai-recommend', 'aiRecommend')
                ->name('matching.ai-recommend');
        });

    // GET /api/chat-profile/filters?lang=eng|jpn&only_enabled=0|1
    Route::get('/chat-profile/filters', [ChatProfileContentController::class, 'filters']);

    // List negotiations (deal-done list)
    Route::get('/matching/negotiations', [MatchingUserController::class, 'negotiationsList']);

    // Mark negotiation as a deal done (status = 4) if both sides already matched
    Route::post('/matching/negotiations', [MatchingUserController::class, 'markDealDone']);
});

Route::controller(WebhookController::class)->group(function (): void {
    Route::post('webhook/csv-list-trigger', 'handleCsvListTriggerWebhook')->name('webhook.csv-list-trigger');
    Route::post('webhook/business-appointment', 'businessAppointment')->name('webhook.business-appointment');
    Route::post('webhook/business-appointment-approved', 'businessAppointmentApproved')->name('webhook.business-appointment-approved');
    Route::post('webhook/business-appointment-rejected', 'businessAppointmentRejected')->name('webhook.business-appointment-rejected');
    Route::post('webhook/business-appointment-cancelled', 'businessAppointmentCancelled')->name('webhook.business-appointment-cancelled');
    Route::post('webhook/business-appointment-rescheduled', 'businessAppointmentRescheduled')->name('webhook.business-appointment-rescheduled');
    Route::post('webhook/user-registration', 'userRegistration')->name('webhook.user-registration');
});

Route::post('reception/checkin', ReceptionCheckinController::class)
    ->name('reception.checkin');

Route::post('reception/checkin/complete', ReceptionCompleteCheckinController::class)
    ->name('reception.checkin.complete');

Route::get('reception/users/{user_uuid}', ReceptionUserShowController::class)
    ->name('reception.users.show');

Route::get('rooms/{room_id}/appointments', RoomAppointmentsController::class)
    ->name('rooms.appointments');

Route::get('rooms/{room_id}', RoomShowController::class)
    ->name('rooms.show');

Route::post('rooms/{room_id}/free-checkin', FreeReceptionCheckinController::class)
    ->name('rooms.free-checkin');

Route::post('appointments/{id}/checkin', AppointmentCheckinController::class)
    ->name('appointments.checkin');
