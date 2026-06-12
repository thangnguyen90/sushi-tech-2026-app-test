<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\MatchingUser;
use App\Models\ReceptionCheckin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentCheckinTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_first_checkin_for_the_first_scan(): void
    {
        Carbon::setTestNow('2026-04-20 15:18:00');
        $this->createApprovedAppointment();

        $response = $this->postJson('/api/v1/appointments/99001/checkin', [
            'user_uuid' => '11111111-1111-4111-8111-111111111111',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('result.appointment_id', 99001)
            ->assertJsonPath('result.checkin_status', 'first_checkin')
            ->assertJsonPath('result.user_uuid', '11111111-1111-4111-8111-111111111111')
            ->assertJsonPath('result.checkin_at', '2026-04-20 15:18:00')
            ->assertJsonPath('result.first_checkin_at', '2026-04-20 15:18:00')
            ->assertJsonPath('result.second_checkin_at', null)
            ->assertJsonPath('result.applicant_user_id', 1001)
            ->assertJsonPath('result.applicant_user_uuid', '11111111-1111-4111-8111-111111111111')
            ->assertJsonPath('result.recipient_user_id', 2002)
            ->assertJsonPath('result.recipient_user_uuid', '22222222-2222-4222-8222-222222222222');

        $this->assertDatabaseHas('reception_checkins', [
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => 15,
            'applicant_user_id' => 1001,
            'applicant_user_uuid' => '11111111-1111-4111-8111-111111111111',
            'recipient_user_id' => 2002,
            'recipient_user_uuid' => '22222222-2222-4222-8222-222222222222',
            'user_uuid' => '11111111-1111-4111-8111-111111111111',
            'checkin_status' => 'first_checkin',
        ]);

        Carbon::setTestNow();
    }

    public function test_it_updates_the_existing_row_to_second_checkin_when_another_user_checks_in(): void
    {
        $this->createApprovedAppointment();

        $checkin = ReceptionCheckin::query()->create([
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => null,
            'user_uuid' => '11111111-1111-4111-8111-111111111111',
            'checkin_status' => 'first_checkin',
            'checkin_at' => '2026-04-20 15:20:00',
        ]);

        Carbon::setTestNow('2026-04-20 15:25:00');

        $response = $this->postJson('/api/v1/appointments/99001/checkin', [
            'user_uuid' => '22222222-2222-4222-8222-222222222222',
        ]);

        $response->assertOk()
            ->assertJsonPath('result.checkin_status', 'second_checkin')
            ->assertJsonPath('result.user_uuid', '22222222-2222-4222-8222-222222222222')
            ->assertJsonPath('result.first_checkin_at', '2026-04-20 15:20:00')
            ->assertJsonPath('result.second_checkin_at', '2026-04-20 15:25:00');

        $this->assertDatabaseHas('reception_checkins', [
            'id' => $checkin->id,
            'appointment_schedule_id' => 99001,
            'applicant_user_id' => 1001,
            'recipient_user_id' => 2002,
            'user_uuid' => '22222222-2222-4222-8222-222222222222',
            'checkin_status' => 'second_checkin',
        ]);
        $this->assertSame(1, ReceptionCheckin::query()->where('appointment_schedule_id', 99001)->count());

        Carbon::setTestNow();
    }

    public function test_it_keeps_first_checkin_when_the_same_user_checks_in_again(): void
    {
        $this->createApprovedAppointment();
        Carbon::setTestNow('2026-04-20 15:30:00');

        $checkin = ReceptionCheckin::query()->create([
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => null,
            'user_uuid' => '11111111-1111-4111-8111-111111111111',
            'checkin_status' => 'first_checkin',
            'checkin_at' => '2026-04-20 15:20:00',
        ]);

        $response = $this->postJson('/api/v1/appointments/99001/checkin', [
            'user_uuid' => '11111111-1111-4111-8111-111111111111',
        ]);

        $response->assertOk()
            ->assertJsonPath('result.checkin_status', 'first_checkin')
            ->assertJsonPath('result.user_uuid', '11111111-1111-4111-8111-111111111111');

        $checkin->refresh();

        $this->assertSame('first_checkin', $checkin->checkin_status);
        $this->assertSame('2026-04-20 15:30:00', $checkin->checkin_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-20 15:30:00', $checkin->first_checkin_at?->format('Y-m-d H:i:s'));
        $this->assertNull($checkin->second_checkin_at);
        $this->assertSame(1, ReceptionCheckin::query()->where('appointment_schedule_id', 99001)->count());

        Carbon::setTestNow();
    }

    public function test_it_keeps_second_checkin_for_later_scans_after_the_pair_is_complete(): void
    {
        $this->createApprovedAppointment();
        Carbon::setTestNow('2026-04-20 15:40:00');

        $checkin = ReceptionCheckin::query()->create([
            'appointment_schedule_id' => 99001,
            'business_appointment_room_id' => null,
            'user_uuid' => '22222222-2222-4222-8222-222222222222',
            'checkin_status' => 'second_checkin',
            'checkin_at' => '2026-04-20 15:22:00',
            'first_checkin_at' => '2026-04-20 15:20:00',
            'second_checkin_at' => '2026-04-20 15:22:00',
        ]);

        $response = $this->postJson('/api/v1/appointments/99001/checkin', [
            'user_uuid' => '33333333-3333-4333-8333-333333333333',
        ]);

        $response->assertOk()
            ->assertJsonPath('result.checkin_status', 'second_checkin')
            ->assertJsonPath('result.user_uuid', '22222222-2222-4222-8222-222222222222');

        $checkin->refresh();

        $this->assertSame('22222222-2222-4222-8222-222222222222', $checkin->user_uuid);
        $this->assertSame('second_checkin', $checkin->checkin_status);
        $this->assertSame('2026-04-20 15:40:00', $checkin->checkin_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-20 15:20:00', $checkin->first_checkin_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-20 15:22:00', $checkin->second_checkin_at?->format('Y-m-d H:i:s'));
        $this->assertSame(1, ReceptionCheckin::query()->where('appointment_schedule_id', 99001)->count());

        Carbon::setTestNow();
    }

    public function test_it_returns_not_found_when_the_approved_appointment_does_not_exist(): void
    {
        $response = $this->postJson('/api/v1/appointments/99001/checkin', [
            'user_uuid' => '11111111-1111-4111-8111-111111111111',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', '商談予約が見つかりません');
    }

    public function test_it_requires_a_valid_uuid(): void
    {
        $response = $this->postJson('/api/v1/appointments/99001/checkin', [
            'user_uuid' => 'not-a-uuid',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('result.message', 'QRコードが不正です');
    }

    private function createApprovedAppointment(): MatchingUser
    {
        return MatchingUser::query()->create([
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
        ]);
    }
}
