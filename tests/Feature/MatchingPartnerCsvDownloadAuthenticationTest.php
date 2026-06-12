<?php

namespace Tests\Feature;

use App\Models\MatchingCsvDownloadSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingPartnerCsvDownloadAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_unauthorized_when_x_encryption_key_is_missing(): void
    {
        MatchingCsvDownloadSetting::query()->create([
            'encryption_key' => 'shared-secret-key',
            'is_enabled' => true,
        ]);

        $response = $this->postJson('/api/v1/matching/csv-download', [
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'live_chat_user_uuids' => ['11111111-1111-4111-8111-111111111111'],
            'language' => 'eng',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Missing x-encryption-key')
            ->assertJsonPath('error', 'Unauthorized');
    }

    public function test_it_returns_unauthorized_when_x_encryption_key_is_invalid(): void
    {
        MatchingCsvDownloadSetting::query()->create([
            'encryption_key' => 'shared-secret-key',
            'is_enabled' => true,
        ]);

        $response = $this->withHeaders([
            'x-encryption-key' => 'wrong-secret-key',
        ])->postJson('/api/v1/matching/csv-download', [
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'live_chat_user_uuids' => ['11111111-1111-4111-8111-111111111111'],
            'language' => 'eng',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Invalid x-encryption-key')
            ->assertJsonPath('error', 'Unauthorized');
    }

    public function test_it_allows_request_when_x_encryption_key_is_valid(): void
    {
        MatchingCsvDownloadSetting::query()->create([
            'encryption_key' => 'shared-secret-key',
            'is_enabled' => true,
        ]);

        $response = $this->withHeaders([
            'x-encryption-key' => 'shared-secret-key',
        ])->postJson('/api/v1/matching/csv-download', [
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'live_chat_user_uuids' => ['11111111-1111-4111-8111-111111111111'],
            'language' => 'eng',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 'OK');
    }
}
