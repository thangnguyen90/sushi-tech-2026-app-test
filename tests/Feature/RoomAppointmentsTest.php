<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\BusinessAppointmentRoom;
use App\Models\LiveChatProfiles;
use App\Models\MatchingUser;
use App\Models\ReceptionCheckin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_approved_room_appointments_for_the_requested_user_uuid(): void
    {
        $this->createRoom(15, 1, '商談エリアB（1階）');
        $this->createRoom(15, 2, 'Meeting Area B (1F)');
        $this->createVisitor();
        $this->createApprovedAppointment();
        $this->createMirroredAppointment();

        $response = $this->withHeaders(['language' => 'jpn'])
            ->getJson('/api/v1/rooms/15/appointments?user_uuid=11111111-1111-4111-8111-111111111111');

        $response->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('message', '')
            ->assertJsonCount(1, 'result.appointments')
            ->assertJsonPath('result.visitor_name', '山田太郎')
            ->assertJsonPath('result.visitor.user_uuid', '11111111-1111-4111-8111-111111111111')
            ->assertJsonPath('result.visitor.user_id', 1001)
            ->assertJsonPath('result.visitor.name', '山田太郎')
            ->assertJsonPath('result.visitor.email', 'taro.yamada@example.com')
            ->assertJsonPath('result.visitor.company_name', 'Sushi Tech Inc.')
            ->assertJsonPath('result.appointments.0.appointment_id', 99001)
            ->assertJsonPath('result.appointments.0.partner_name', 'Bob Recipient')
            ->assertJsonPath('result.appointments.0.schedule_time', '2026-04-20 15:30:00')
            ->assertJsonPath('result.appointments.0.room_id', 15)
            ->assertJsonPath('result.appointments.0.room_name', '商談エリアB（1階）')
            ->assertJsonPath('result.appointments.0.checkin_status', null)
            ->assertJsonPath('result.appointments.0.user_uuid', null);
    }

    public function test_it_uses_english_room_name_when_language_header_is_eng(): void
    {
        $this->createRoom(15, 1, '商談エリアB（1階）');
        $this->createRoom(15, 2, 'Meeting Area B (1F)');
        $this->createVisitor();
        $this->createApprovedAppointment();

        $response = $this->withHeaders(['language' => 'eng'])
            ->getJson('/api/v1/rooms/15/appointments?user_uuid=11111111-1111-4111-8111-111111111111');

        $response->assertOk()
            ->assertJsonPath('result.appointments.0.room_name', 'Meeting Area B (1F)');
    }

    public function test_it_falls_back_to_japanese_room_name_for_unsupported_language_header(): void
    {
        $this->createRoom(15, 1, '商談エリアB（1階）');
        $this->createRoom(15, 2, 'Meeting Area B (1F)');
        $this->createVisitor();
        $this->createApprovedAppointment();

        $response = $this->withHeaders(['language' => 'vi'])
            ->getJson('/api/v1/rooms/15/appointments?user_uuid=11111111-1111-4111-8111-111111111111');

        $response->assertOk()
            ->assertJsonPath('result.appointments.0.room_name', '商談エリアB（1階）');
    }

    public function test_it_still_returns_appointments_when_a_first_checkin_record_exists(): void
    {
        $this->createRoom(15, 1, '商談エリアB（1階）');
        $this->createVisitor();
        $this->createApprovedAppointment();

        ReceptionCheckin::query()->create([
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => null,
            'user_uuid' => '11111111-1111-4111-8111-111111111111',
            'checkin_status' => 'first_checkin',
            'checkin_at' => '2026-04-20 15:20:00',
        ]);

        $response = $this->getJson('/api/v1/rooms/15/appointments?user_uuid=11111111-1111-4111-8111-111111111111');

        $response->assertOk()
            ->assertJsonPath('result.appointments.0.appointment_id', 99001)
            ->assertJsonPath('result.appointments.0.checkin_status', 'first_checkin')
            ->assertJsonPath('result.appointments.0.user_uuid', '11111111-1111-4111-8111-111111111111');
    }

    public function test_it_still_returns_appointments_when_second_checkin_records_exist(): void
    {
        $this->createRoom(15, 1, '商談エリアB（1階）');
        $this->createVisitor();
        $this->createApprovedAppointment();

        ReceptionCheckin::query()->create([
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => null,
            'user_uuid' => '11111111-1111-4111-8111-111111111111',
            'checkin_status' => 'first_checkin',
            'checkin_at' => '2026-04-20 15:20:00',
        ]);
        ReceptionCheckin::query()->create([
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => null,
            'user_uuid' => '22222222-2222-4222-8222-222222222222',
            'checkin_status' => 'second_checkin',
            'checkin_at' => '2026-04-20 15:22:00',
        ]);

        $response = $this->getJson('/api/v1/rooms/15/appointments?user_uuid=11111111-1111-4111-8111-111111111111');

        $response->assertOk()
            ->assertJsonPath('result.appointments.0.appointment_id', 99001)
            ->assertJsonPath('result.appointments.0.checkin_status', 'second_checkin')
            ->assertJsonPath('result.appointments.0.user_uuid', '22222222-2222-4222-8222-222222222222');
    }

    public function test_it_returns_all_approved_appointments_for_the_requested_user_including_other_rooms(): void
    {
        $this->createRoom(15, 1, '商談エリアB（1階）');
        $this->createRoom(16, 1, '商談エリアC（1階）');
        $this->createVisitor();
        $this->createApprovedAppointment();
        $this->createApprovedAppointment([
            'appointment_schedule_id' => 99002,
            'business_appointment_room_id' => 16,
        ]);
        $this->createApprovedAppointment([
            'appointment_schedule_id' => 99004,
            'business_appointment_room_id' => null,
        ]);
        $this->createApprovedAppointment([
            'appointment_schedule_id' => 99003,
            'appointment_status' => AppointmentStatus::Rejected->value,
        ]);

        $response = $this->getJson('/api/v1/rooms/15/appointments?user_uuid=11111111-1111-4111-8111-111111111111');

        $response->assertOk()
            ->assertJsonCount(3, 'result.appointments')
            ->assertJsonFragment([
                'appointment_id' => 99004,
                'room_id' => null,
                'room_name' => '',
                'checkin_status' => null,
                'user_uuid' => null,
            ])
            ->assertJsonFragment([
                'appointment_id' => 99001,
                'room_id' => 15,
                'room_name' => '商談エリアB（1階）',
                'checkin_status' => null,
                'user_uuid' => null,
            ])
            ->assertJsonFragment([
                'appointment_id' => 99002,
                'room_id' => 16,
                'room_name' => '商談エリアC（1階）',
                'checkin_status' => null,
                'user_uuid' => null,
            ]);
    }

    public function test_it_returns_success_when_the_user_has_approved_appointments_only_in_other_rooms(): void
    {
        $this->createRoom(15, 1, '商談エリアB（1階）');
        $this->createRoom(16, 1, '商談エリアC（1階）');
        $this->createVisitor();
        $this->createApprovedAppointment([
            'appointment_schedule_id' => 99002,
            'business_appointment_room_id' => 16,
        ]);

        $response = $this->getJson('/api/v1/rooms/15/appointments?user_uuid=11111111-1111-4111-8111-111111111111');

        $response->assertOk()
            ->assertJsonCount(1, 'result.appointments')
            ->assertJsonPath('result.appointments.0.appointment_id', 99002)
            ->assertJsonPath('result.appointments.0.room_id', 16)
            ->assertJsonPath('result.appointments.0.room_name', '商談エリアC（1階）');
    }

    public function test_it_returns_validation_error_for_invalid_uuid(): void
    {
        $response = $this->getJson('/api/v1/rooms/15/appointments?user_uuid=not-a-uuid');

        $response->assertStatus(400)
            ->assertJsonPath('result.message', 'QRコードが不正です');
    }

    public function test_it_returns_not_found_when_no_approved_reservation_exists_for_room(): void
    {
        $this->createRoom(15, 1, '商談エリアB（1階）');

        $response = $this->getJson('/api/v1/rooms/15/appointments?user_uuid=11111111-1111-4111-8111-111111111111');

        $response->assertStatus(404)
            ->assertJsonPath('message', '商談予約をしていないユーザです')
            ->assertJsonPath('result.visitor.user_uuid', '11111111-1111-4111-8111-111111111111')
            ->assertJsonPath('result.visitor.name', null);
    }

    private function createRoom(int $roomId, int $languageId, string $name): void
    {
        BusinessAppointmentRoom::query()->create([
            'business_appointment_room_id' => $roomId,
            'name' => $name,
            'language_id' => $languageId,
        ]);
    }

    private function createVisitor(): LiveChatProfiles
    {
        return LiveChatProfiles::query()->create([
            'profile_id' => 10001,
            'user_id' => 1001,
            'user_uuid' => '11111111-1111-4111-8111-111111111111',
            'uuid' => 'profile-uuid-10001',
            'nickname' => '山田太郎',
            'mail_address' => 'taro.yamada@example.com',
            'company' => 'Sushi Tech Inc.',
            'created_at' => '2026-04-16 10:00:00',
            'updated_at' => '2026-04-16 10:00:00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createApprovedAppointment(array $overrides = []): MatchingUser
    {
        $attributes = [
            'event_id' => 77,
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => 15,
            'schedule_start_datetime' => '2026-04-20 15:30:00',
            'appointment_status' => AppointmentStatus::Approved->value,
            'webhook_module_code' => 'BusinessAppointmentApproved',
            'webhook_schedule_status' => 'Approved',
            'owner_user_id' => 1001,
            'owner_uuid' => '11111111-1111-4111-8111-111111111111',
            'peer_user_id' => 2002,
            'peer_uuid' => '22222222-2222-4222-8222-222222222222',
            'status' => 4,
            'webhook_data' => [
                'applicant' => [
                    'user' => [
                        'user_id' => 1001,
                        'user_uuid' => '11111111-1111-4111-8111-111111111111',
                        'name' => 'Alice Applicant',
                    ],
                ],
                'recipient' => [
                    'user' => [
                        'user_id' => 2002,
                        'user_uuid' => '22222222-2222-4222-8222-222222222222',
                        'name' => 'Bob Recipient',
                    ],
                ],
                'exhibitor_administrator_appointment_schedule_detail' => [
                    'applicant_name' => 'Alice Applicant',
                    'recipient_name' => 'Bob Recipient',
                ],
            ],
        ];

        return MatchingUser::query()->create(array_replace($attributes, $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMirroredAppointment(array $overrides = []): MatchingUser
    {
        $attributes = [
            'event_id' => 77,
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => 15,
            'schedule_start_datetime' => '2026-04-20 15:30:00',
            'appointment_status' => AppointmentStatus::Approved->value,
            'webhook_module_code' => 'BusinessAppointmentApproved',
            'webhook_schedule_status' => 'Approved',
            'owner_user_id' => 2002,
            'owner_uuid' => '22222222-2222-4222-8222-222222222222',
            'peer_user_id' => 1001,
            'peer_uuid' => '11111111-1111-4111-8111-111111111111',
            'status' => 4,
            'webhook_data' => [
                'applicant' => [
                    'user' => [
                        'user_uuid' => '11111111-1111-4111-8111-111111111111',
                        'name' => 'Alice Applicant',
                    ],
                ],
                'recipient' => [
                    'user' => [
                        'user_uuid' => '22222222-2222-4222-8222-222222222222',
                        'name' => 'Bob Recipient',
                    ],
                ],
                'exhibitor_administrator_appointment_schedule_detail' => [
                    'applicant_name' => 'Alice Applicant',
                    'recipient_name' => 'Bob Recipient',
                ],
            ],
        ];

        return MatchingUser::query()->create(array_replace($attributes, $overrides));
    }
}
