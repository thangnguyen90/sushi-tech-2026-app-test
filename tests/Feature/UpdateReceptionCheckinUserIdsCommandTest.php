<?php

namespace Tests\Feature;

use App\Models\ReceptionCheckin;
use App\Services\Eventos\User\UserService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class UpdateReceptionCheckinUserIdsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_missing_reception_checkin_user_ids_by_matching_user_qrcode(): void
    {
        $checkin = ReceptionCheckin::query()->create([
            'appointment_schedule_id' => null,
            'business_appointment_room_id' => 22,
            'applicant_user_id' => null,
            'applicant_user_uuid' => 'applicant-uuid',
            'recipient_user_id' => null,
            'recipient_user_uuid' => 'recipient-uuid',
            'user_uuid' => 'recipient-uuid',
            'checkin_status' => 'second_checkin',
            'checkin_at' => '2026-04-27 10:00:00',
            'first_checkin_at' => '2026-04-27 10:00:00',
            'second_checkin_at' => '2026-04-27 10:00:00',
        ]);

        $this->mock(UserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forEachUserListPageParallel')
                ->once()
                ->andReturnUsing(function (callable $pageProcessor): void {
                    $pageProcessor([
                        'total' => 2,
                        'data' => [
                            ['user_id' => 101, 'user_qrcode' => 'QR-101'],
                            ['user_id' => 202, 'user_qrcode' => 'QR-202'],
                        ],
                    ], 1);
                });

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('applicant-uuid', 0)
                ->andReturn([
                    'data' => [
                        'user_qrcode' => 'QR-101',
                    ],
                ]);

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('recipient-uuid', 0)
                ->andReturn([
                    'data' => [
                        'user_qrcode' => 'QR-202',
                    ],
                ]);
        });

        $this->artisan('app:update-reception-checkin-user-ids')
            ->expectsOutput('Processed 1 checkins. UpdatedApplicant=1 UpdatedRecipient=1 SkippedMissingQr=0 SkippedMissingMatch=0 Failed=0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reception_checkins', [
            'id' => $checkin->id,
            'applicant_user_id' => 101,
            'recipient_user_id' => 202,
        ]);
    }

    public function test_it_skips_when_user_qrcode_cannot_be_resolved_or_matched(): void
    {
        $skippedCheckin = ReceptionCheckin::query()->create([
            'appointment_schedule_id' => null,
            'business_appointment_room_id' => 23,
            'applicant_user_id' => null,
            'applicant_user_uuid' => 'missing-qr-uuid',
            'recipient_user_id' => null,
            'recipient_user_uuid' => 'missing-match-uuid',
            'user_uuid' => 'missing-match-uuid',
            'checkin_status' => 'second_checkin',
            'checkin_at' => '2026-04-27 10:10:00',
            'first_checkin_at' => '2026-04-27 10:10:00',
            'second_checkin_at' => '2026-04-27 10:10:00',
        ]);
        $updatedCheckin = ReceptionCheckin::query()->create([
            'appointment_schedule_id' => null,
            'business_appointment_room_id' => 24,
            'applicant_user_id' => null,
            'applicant_user_uuid' => 'ok-uuid',
            'recipient_user_id' => 999,
            'recipient_user_uuid' => 'already-set-uuid',
            'user_uuid' => 'already-set-uuid',
            'checkin_status' => 'second_checkin',
            'checkin_at' => '2026-04-27 10:20:00',
            'first_checkin_at' => '2026-04-27 10:20:00',
            'second_checkin_at' => '2026-04-27 10:20:00',
        ]);

        $this->mock(UserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forEachUserListPageParallel')
                ->once()
                ->andReturnUsing(function (callable $pageProcessor): void {
                    $pageProcessor([
                        'total' => 1,
                        'data' => [
                            ['user_id' => 303, 'user_qrcode' => 'QR-303'],
                        ],
                    ], 1);
                });

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('missing-qr-uuid', 0)
                ->andReturn([
                    'data' => [],
                ]);

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('missing-match-uuid', 0)
                ->andReturn([
                    'data' => [
                        'user_qrcode' => 'QR-404',
                    ],
                ]);

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('ok-uuid', 0)
                ->andReturn([
                    'data' => [
                        'user_qrcode' => 'QR-303',
                    ],
                ]);
        });

        $this->artisan('app:update-reception-checkin-user-ids')
            ->expectsOutput('Processed 2 checkins. UpdatedApplicant=1 UpdatedRecipient=0 SkippedMissingQr=1 SkippedMissingMatch=1 Failed=0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reception_checkins', [
            'id' => $skippedCheckin->id,
            'applicant_user_id' => null,
            'recipient_user_id' => null,
        ]);
        $this->assertDatabaseHas('reception_checkins', [
            'id' => $updatedCheckin->id,
            'applicant_user_id' => 303,
            'recipient_user_id' => 999,
        ]);
    }

    public function test_it_continues_when_a_user_detail_request_fails(): void
    {
        $failedCheckin = ReceptionCheckin::query()->create([
            'appointment_schedule_id' => null,
            'business_appointment_room_id' => 25,
            'applicant_user_id' => null,
            'applicant_user_uuid' => 'failed-uuid',
            'recipient_user_id' => null,
            'recipient_user_uuid' => null,
            'user_uuid' => 'failed-uuid',
            'checkin_status' => 'first_checkin',
            'checkin_at' => '2026-04-27 10:30:00',
            'first_checkin_at' => '2026-04-27 10:30:00',
            'second_checkin_at' => null,
        ]);
        $updatedCheckin = ReceptionCheckin::query()->create([
            'appointment_schedule_id' => null,
            'business_appointment_room_id' => 26,
            'applicant_user_id' => null,
            'applicant_user_uuid' => 'updated-uuid',
            'recipient_user_id' => null,
            'recipient_user_uuid' => null,
            'user_uuid' => 'updated-uuid',
            'checkin_status' => 'first_checkin',
            'checkin_at' => '2026-04-27 10:40:00',
            'first_checkin_at' => '2026-04-27 10:40:00',
            'second_checkin_at' => null,
        ]);

        $this->mock(UserService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('forEachUserListPageParallel')
                ->once()
                ->andReturnUsing(function (callable $pageProcessor): void {
                    $pageProcessor([
                        'total' => 1,
                        'data' => [
                            ['user_id' => 404, 'user_qrcode' => 'QR-404'],
                        ],
                    ], 1);
                });

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('failed-uuid', 0)
                ->andThrow(new Exception('Eventos error'));

            $mock->shouldReceive('getUsersByUuid')
                ->once()
                ->with('updated-uuid', 0)
                ->andReturn([
                    'data' => [
                        'user_qrcode' => 'QR-404',
                    ],
                ]);
        });

        $this->artisan('app:update-reception-checkin-user-ids')
            ->expectsOutput('Processed 2 checkins. UpdatedApplicant=1 UpdatedRecipient=0 SkippedMissingQr=0 SkippedMissingMatch=0 Failed=1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('reception_checkins', [
            'id' => $failedCheckin->id,
            'applicant_user_id' => null,
        ]);
        $this->assertDatabaseHas('reception_checkins', [
            'id' => $updatedCheckin->id,
            'applicant_user_id' => 404,
        ]);
    }
}
