<?php

namespace Tests\Feature;

use App\Models\BusinessAppointmentRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_localized_room_name_for_requested_room(): void
    {
        BusinessAppointmentRoom::query()->create([
            'business_appointment_room_id' => 22,
            'name' => '商談エリアA（1階）',
            'language_id' => 1,
        ]);
        BusinessAppointmentRoom::query()->create([
            'business_appointment_room_id' => 22,
            'name' => 'Meeting Area A (1F)',
            'language_id' => 2,
        ]);

        $response = $this->withHeaders(['language' => 'jpn'])
            ->getJson('/api/v1/rooms/22');

        $response->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('message', '')
            ->assertJsonPath('result.room_id', 22)
            ->assertJsonPath('result.is_free', false)
            ->assertJsonPath('result.room_name', '商談エリアA（1階）');
    }

    public function test_it_returns_english_room_name_when_language_header_is_eng(): void
    {
        BusinessAppointmentRoom::query()->create([
            'business_appointment_room_id' => 22,
            'name' => '商談エリアA（1階）',
            'language_id' => 1,
        ]);
        BusinessAppointmentRoom::query()->create([
            'business_appointment_room_id' => 22,
            'name' => 'Meeting Area A (1F)',
            'language_id' => 2,
        ]);

        $response = $this->withHeaders(['language' => 'eng'])
            ->getJson('/api/v1/rooms/22');

        $response->assertOk()
            ->assertJsonPath('result.room_name', 'Meeting Area A (1F)');
    }

    public function test_it_falls_back_to_japanese_room_name_for_unsupported_language_header(): void
    {
        BusinessAppointmentRoom::query()->create([
            'business_appointment_room_id' => 22,
            'name' => '商談エリアA（1階）',
            'language_id' => 1,
        ]);

        $response = $this->withHeaders(['language' => 'vi'])
            ->getJson('/api/v1/rooms/22');

        $response->assertOk()
            ->assertJsonPath('result.room_name', '商談エリアA（1階）');
    }

    public function test_it_returns_not_found_when_room_does_not_exist(): void
    {
        $response = $this->getJson('/api/v1/rooms/22');

        $response->assertStatus(404)
            ->assertJsonPath('message', '商談場所が存在しません')
            ->assertJsonPath('result.room_id', 22)
            ->assertJsonPath('result.is_free', false)
            ->assertJsonPath('result.room_name', null);
    }
}
