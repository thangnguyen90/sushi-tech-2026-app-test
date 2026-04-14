<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\BizTalks;
use App\Models\MatchingUser;
use App\Repositories\MatchingUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentWebhookSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_bidirectional_rows_for_an_approved_appointment_webhook(): void
    {
        $response = $this->postJson(
            '/api/v1/webhook/business-appointment-approved',
            $this->appointmentPayload('BusinessAppointmentApproved')
        );

        $response->assertCreated();

        $this->assertDatabaseHas('matching_users', [
            'owner_user_id' => 1001,
            'peer_user_id' => 2002,
            'event_id' => 77,
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => 15,
            'appointment_status' => AppointmentStatus::Approved->value,
            'webhook_module_code' => 'BusinessAppointmentApproved',
            'webhook_schedule_status' => 'Approved',
            'status' => 4,
        ]);
        $this->assertDatabaseHas('matching_users', [
            'owner_user_id' => 2002,
            'peer_user_id' => 1001,
            'event_id' => 77,
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => 15,
            'appointment_status' => AppointmentStatus::Approved->value,
            'webhook_module_code' => 'BusinessAppointmentApproved',
            'webhook_schedule_status' => 'Approved',
            'status' => 4,
        ]);
        $this->assertDatabaseHas('biz_talks', [
            'exhibitor_schedule_id' => 99001,
            'user_id' => 1001,
            'user_uuid' => 'user-1001',
        ]);

        $matchingUser = MatchingUser::query()
            ->where('owner_user_id', 1001)
            ->where('peer_user_id', 2002)
            ->firstOrFail();

        $this->assertSame('BusinessAppointmentApproved', $matchingUser->webhook_data['module_code']);
    }

    #[DataProvider('appointmentStatusProvider')]
    public function test_it_updates_existing_rows_for_supported_non_approved_hooks(
        string $endpoint,
        string $moduleCode,
        string $expectedRawStatus
    ): void {
        $payload = $this->appointmentPayload($moduleCode, [
            'exhibitor_administrator_appointment_schedule_detail' => [
                'schedule_start_datetime' => '2026-04-20 16:45:00',
            ],
        ]);

        $response = $this->postJson($endpoint, $payload);

        $response->assertCreated();

        $this->assertSame(
            2,
            MatchingUser::query()->where('appointment_schedule_id', 99001)->count()
        );
        $this->assertDatabaseHas('matching_users', [
            'owner_user_id' => 1001,
            'peer_user_id' => 2002,
            'appointment_schedule_id' => 99001,
            'appointment_status' => AppointmentStatus::fromModuleCode($moduleCode)?->value,
            'webhook_module_code' => $moduleCode,
            'webhook_schedule_status' => $expectedRawStatus,
        ]);
        $this->assertDatabaseHas('biz_talks', [
            'exhibitor_schedule_id' => 99001,
            'user_id' => 1001,
            'user_uuid' => 'user-1001',
            'started_at' => '2026-04-20 16:45:00',
        ]);

        $bizTalk = BizTalks::query()
            ->where('exhibitor_schedule_id', 99001)
            ->firstOrFail();

        $data = json_decode((string) $bizTalk->data, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame($moduleCode, $data['module_code']);
        $this->assertSame(
            $expectedRawStatus,
            $data['exhibitor_administrator_appointment_schedule_detail']['status']
        );

        $matchingUser = MatchingUser::query()
            ->where('owner_user_id', 1001)
            ->where('peer_user_id', 2002)
            ->where('appointment_schedule_id', 99001)
            ->firstOrFail();

        $this->assertSame('2026-04-20 16:45:00', $matchingUser->getRawOriginal('schedule_start_datetime'));
        $this->assertSame($moduleCode, $matchingUser->webhook_data['module_code']);
    }

    public function test_it_keeps_legacy_status_four_rows_visible_as_approved_records(): void
    {
        MatchingUser::query()->create([
            'event_id' => 77,
            'owner_user_id' => 1001,
            'owner_uuid' => 'user-1001',
            'peer_user_id' => 2002,
            'peer_uuid' => 'user-2002',
            'status' => 4,
        ]);

        $rows = app(MatchingUserRepository::class)->getDealDoneListAllForOwner(1001, 4);

        $this->assertSame(['user-2002'], $rows->pluck('peer_uuid')->all());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function appointmentStatusProvider(): array
    {
        return [
            'applied' => ['/api/v1/webhook/business-appointment', 'BusinessAppointment', 'Pending'],
            'cancelled' => ['/api/v1/webhook/business-appointment-cancelled', 'BusinessAppointmentCancel', 'Cancel'],
            'rejected' => ['/api/v1/webhook/business-appointment-rejected', 'BusinessAppointmentReject', 'Reject'],
            'rescheduled' => ['/api/v1/webhook/business-appointment-rescheduled', 'BusinessAppointmentReschedule', 'Pending'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function appointmentPayload(string $moduleCode, array $overrides = []): array
    {
        $payload = [
            'module_code' => $moduleCode,
            'basic_information' => [
                'event_id' => 77,
            ],
            'applicant' => [
                'user' => [
                    'user_id' => 1001,
                    'user_uuid' => 'user-1001',
                    'name' => 'Alice Applicant',
                    'company_name' => 'Applicant Co',
                ],
            ],
            'recipient' => [
                'user' => [
                    'user_id' => 2002,
                    'user_uuid' => 'user-2002',
                    'name' => 'Bob Recipient',
                    'company_name' => 'Recipient Co',
                ],
            ],
            'exhibitor_administrator_appointment_schedule_detail' => [
                'id' => 99001,
                'business_appointment_room_id' => 15,
                'schedule_start_datetime' => '2026-04-20 15:30:00',
                'status' => $this->resolveScheduleStatus($moduleCode),
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }

    private function resolveScheduleStatus(string $moduleCode): string
    {
        return match ($moduleCode) {
            'BusinessAppointmentApproved' => 'Approved',
            'BusinessAppointmentCancel' => 'Cancel',
            'BusinessAppointmentReject' => 'Reject',
            default => 'Pending',
        };
    }
}
