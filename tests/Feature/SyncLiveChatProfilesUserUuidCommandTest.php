<?php

namespace Tests\Feature;

use App\Models\LiveChatProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncLiveChatProfilesUserUuidCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_user_uuid_and_inserts_missing_profiles_from_local_disk_csv(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/live_chat_profiles_user_uuid.csv', $this->csvContent([
            [
                'id' => '10001',
                'user_id' => '501',
                'uuid' => 'profile-uuid-1',
                'nickname' => 'Alice',
                'mail_address' => 'alice@example.com',
                'exhibitor_text' => '',
                'user_uuid' => '11111111-1111-4111-8111-111111111111',
                'created_at' => '2026-4-16, 10:00',
                'updated_at' => '2026-4-16, 10:05',
            ],
            [
                'id' => '10002',
                'user_id' => '502',
                'uuid' => 'profile-uuid-2',
                'nickname' => 'Bob',
                'mail_address' => 'bob@example.com',
                'exhibitor_text' => '出展者',
                'user_uuid' => '22222222-2222-4222-8222-222222222222',
                'created_at' => '2026-4-16, 10:00',
                'updated_at' => '2026-4-16, 10:06',
            ],
            [
                'id' => '99999',
                'user_id' => '503',
                'uuid' => 'profile-uuid-3',
                'nickname' => 'Carol',
                'mail_address' => 'carol@example.com',
                'exhibitor_text' => '',
                'user_uuid' => '33333333-3333-4333-8333-333333333333',
                'created_at' => '2026-4-16, 10:07',
                'updated_at' => '2026-4-16, 10:08',
            ],
            [
                'id' => '10003',
                'user_id' => '504',
                'uuid' => 'profile-uuid-4',
                'nickname' => 'Invalid',
                'mail_address' => 'invalid@example.com',
                'exhibitor_text' => '',
                'user_uuid' => 'not-a-uuid',
                'created_at' => '2026-4-16, 10:09',
                'updated_at' => '2026-4-16, 10:10',
            ],
        ]));

        LiveChatProfiles::query()->create([
            'profile_id' => 10001,
            'user_id' => 501,
            'uuid' => 'profile-uuid-1',
            'user_uuid' => null,
            'created_at' => '2026-04-16 10:00:00',
            'updated_at' => '2026-04-16 10:00:00',
        ]);

        LiveChatProfiles::query()->create([
            'profile_id' => 10002,
            'user_id' => 502,
            'uuid' => 'profile-uuid-2',
            'user_uuid' => null,
            'created_at' => '2026-04-16 10:00:00',
            'updated_at' => '2026-04-16 10:00:00',
        ]);

        $this->artisan('live_chat_profiles:user_uuid', [
            'file' => 'imports/live_chat_profiles_user_uuid.csv',
            '--disk' => 'local',
            '--chunk' => 2,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('live_chat_profiles', [
            'profile_id' => 10001,
            'user_uuid' => '11111111-1111-4111-8111-111111111111',
        ]);
        $this->assertDatabaseHas('live_chat_profiles', [
            'profile_id' => 10002,
            'user_uuid' => '22222222-2222-4222-8222-222222222222',
            'is_exhibitor' => true,
        ]);
        $this->assertDatabaseHas('live_chat_profiles', [
            'profile_id' => 99999,
            'user_uuid' => '33333333-3333-4333-8333-333333333333',
            'nickname' => 'Carol',
            'mail_address' => 'carol@example.com',
        ]);
        $this->assertDatabaseMissing('live_chat_profiles', [
            'profile_id' => 10003,
        ]);
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function csvContent(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, [
            'id',
            'user_id',
            'uuid',
            'nickname',
            'mail_address',
            'exhibitor_text',
            'user_uuid',
            'created_at',
            'updated_at',
        ]);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['id'],
                $row['user_id'],
                $row['uuid'],
                $row['nickname'],
                $row['mail_address'],
                $row['exhibitor_text'],
                $row['user_uuid'],
                $row['created_at'],
                $row['updated_at'],
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
