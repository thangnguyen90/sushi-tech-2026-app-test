<?php

namespace Tests\Feature;

use App\Models\BusinessAppointmentRoom;
use App\Models\ReceptionCheckin;
use App\Services\Eventos\User\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class FreeReceptionCheckinTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_second_checkin_row_for_a_free_room(): void
    {
        $this->createFreeRoom();
        $this->mockEventosUsers();

        $response = $this->postJson('/api/v1/rooms/22/free-checkin', [
            'first_user_uuid' => '11111111-1111-4111-8111-111111111111',
            'second_user_uuid' => '22222222-2222-4222-8222-222222222222',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('result.room_id', 22)
            ->assertJsonPath('result.checkin_status', 'second_checkin')
            ->assertJsonPath('result.user_uuid', '22222222-2222-4222-8222-222222222222')
            ->assertJsonPath('result.applicant_user_id', null)
            ->assertJsonPath('result.recipient_user_id', null);

        $this->assertDatabaseHas('reception_checkins', [
            'appointment_schedule_id' => null,
            'business_appointment_room_id' => 22,
            'user_uuid' => '22222222-2222-4222-8222-222222222222',
            'applicant_user_id' => null,
            'recipient_user_id' => null,
            'checkin_status' => 'second_checkin',
        ]);
        $this->assertSame(1, ReceptionCheckin::query()->where('business_appointment_room_id', 22)->whereNull('appointment_schedule_id')->count());
    }

    public function test_it_rejects_non_free_rooms(): void
    {
        BusinessAppointmentRoom::query()->create([
            'business_appointment_room_id' => 22,
            'name' => '商談エリアA（1階）',
            'language_id' => 1,
            'is_free' => false,
        ]);
        $this->mockEventosUsers();

        $response = $this->postJson('/api/v1/rooms/22/free-checkin', [
            'first_user_uuid' => '11111111-1111-4111-8111-111111111111',
            'second_user_uuid' => '22222222-2222-4222-8222-222222222222',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'フリー受付対象の商談場所ではありません');
    }

    public function test_it_requires_two_different_users(): void
    {
        $this->createFreeRoom();

        $response = $this->postJson('/api/v1/rooms/22/free-checkin', [
            'first_user_uuid' => '11111111-1111-4111-8111-111111111111',
            'second_user_uuid' => '11111111-1111-4111-8111-111111111111',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('result.message', '同じ来場者は追加できません');
    }

    private function createFreeRoom(): void
    {
        BusinessAppointmentRoom::query()->create([
            'business_appointment_room_id' => 22,
            'name' => '商談エリアA（1階）',
            'language_id' => 1,
            'is_free' => true,
        ]);
    }

    private function mockEventosUsers(): void
    {
        $usersByUuid = [
            '11111111-1111-4111-8111-111111111111' => [
                'account' => 'taro.event@example.com',
                'user_qrcode' => '11111111-1111-4111-8111-111111111111',
                'profiles' => [
                    'share_profiles' => [
                        [
                            'share_profile_id' => 1001,
                            'text_value' => null,
                            'selector_value' => [
                                [
                                    'key' => 'last_name',
                                    'value' => '山田',
                                ],
                                [
                                    'key' => 'first_name',
                                    'value' => '太郎',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '22222222-2222-4222-8222-222222222222' => [
                'account' => 'bob.recipient@example.com',
                'external_qrcode' => '22222222-2222-4222-8222-222222222222',
                'profiles' => [
                    'share_profiles' => [
                        [
                            'share_profile_id' => 1001,
                            'text_value' => null,
                            'selector_value' => [
                                [
                                    'key' => 'last_name',
                                    'value' => 'Bob',
                                ],
                                [
                                    'key' => 'first_name',
                                    'value' => 'Recipient',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->mock(UserService::class, function (MockInterface $mock) use ($usersByUuid): void {
            $mock->shouldReceive('getUsersByUuid')
                ->andReturnUsing(function (string $uuid, int $cacheDuration = 0) use ($usersByUuid): array {
                    if (! isset($usersByUuid[$uuid])) {
                        throw new \Exception('User not found', 404);
                    }

                    return $usersByUuid[$uuid];
                });
        });
    }
}
