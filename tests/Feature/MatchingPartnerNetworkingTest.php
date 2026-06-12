<?php

namespace Tests\Feature;

use App\Models\CheckinHistory;
use App\Models\LiveChatProfiles;
use App\Models\NetworkingEventMaster;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingPartnerNetworkingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-04-27 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_groups_networking_results_by_master_event_name_when_checkin_names_change(): void
    {
        $actor = $this->createProfile([
            'profile_id' => 5001,
            'user_id' => 1001,
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'nickname' => 'Current User',
        ]);

        $candidateOne = $this->createProfile([
            'profile_id' => 5002,
            'user_id' => 2001,
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'nickname' => 'Visitor One',
        ]);

        $candidateTwo = $this->createProfile([
            'profile_id' => 5003,
            'user_id' => 2002,
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'nickname' => 'Visitor Two',
        ]);

        $this->createNetworkingEventMaster('ネットワーキングイベントZ', 'Networking Event Z', 'Networking Event Z (Legacy)');
        $this->createNetworkingEventMaster('ネットワーキングイベントZ', 'Networking Event Z', 'Networking Event Z (Current)');

        $this->createCheckinHistory($actor->user_id, 'Networking Event Z (Legacy)');
        $this->createCheckinHistory($actor->user_id, 'Networking Event Z (Current)');

        $this->createCheckinHistory($candidateOne->user_id, 'Networking Event Z (Legacy)');
        $this->createCheckinHistory($candidateOne->user_id, 'Networking Event Z (Current)');
        $this->createCheckinHistory($candidateTwo->user_id, 'Networking Event Z (Current)');

        $response = $this->withHeaders([
            'user-uuid' => $actor->uuid,
        ])->getJson('/api/v1/matching-partners');

        $response->assertOk()
            ->assertJsonPath('result.0.discover_type', 'NETWORKING')
            ->assertJsonCount(1, 'result.0.list')
            ->assertJsonPath('result.0.list.0.checkin_app_user_name', 'ネットワーキングイベントZ')
            ->assertJsonCount(2, 'result.0.list.0.items')
            ->assertJsonFragment([
                'nickname' => 'Visitor One',
            ])
            ->assertJsonFragment([
                'nickname' => 'Visitor Two',
            ]);
    }

    public function test_it_returns_english_networking_group_name_when_language_header_is_eng(): void
    {
        $actor = $this->createProfile([
            'profile_id' => 6001,
            'user_id' => 1101,
            'uuid' => '44444444-4444-4444-8444-444444444444',
            'nickname' => 'Current User',
        ]);

        $candidate = $this->createProfile([
            'profile_id' => 6002,
            'user_id' => 2101,
            'uuid' => '55555555-5555-4555-8555-555555555555',
            'nickname' => 'Visitor English',
        ]);

        $this->createNetworkingEventMaster('ネットワーキングイベントY', 'Networking Event Y', 'Networking Event Y (Legacy)');
        $this->createNetworkingEventMaster('ネットワーキングイベントY', 'Networking Event Y', 'Networking Event Y (Current)');
        $this->createCheckinHistory($actor->user_id, 'Networking Event Y (Legacy)');
        $this->createCheckinHistory($actor->user_id, 'Networking Event Y (Current)');
        $this->createCheckinHistory($candidate->user_id, 'Networking Event Y (Current)');

        $response = $this->withHeaders([
            'user-uuid' => $actor->uuid,
            'language' => 'eng',
        ])->getJson('/api/v1/matching-partners');

        $response->assertOk()
            ->assertJsonCount(1, 'result.0.list')
            ->assertJsonPath('result.0.list.0.checkin_app_user_name', 'Networking Event Y')
            ->assertJsonCount(1, 'result.0.list.0.items')
            ->assertJsonFragment([
                'nickname' => 'Visitor English',
            ]);
    }

    public function test_it_only_returns_networking_events_after_matching_display_time_is_reached(): void
    {
        CarbonImmutable::setTestNow('2026-04-20 14:00:00');

        $actor = $this->createProfile([
            'profile_id' => 7001,
            'user_id' => 1201,
            'uuid' => '66666666-6666-4666-8666-666666666666',
            'nickname' => 'Current User',
        ]);

        $visibleCandidate = $this->createProfile([
            'profile_id' => 7002,
            'user_id' => 2201,
            'uuid' => '77777777-7777-4777-8777-777777777777',
            'nickname' => 'Visible Visitor',
        ]);

        $hiddenCandidate = $this->createProfile([
            'profile_id' => 7003,
            'user_id' => 2202,
            'uuid' => '88888888-8888-4888-8888-888888888888',
            'nickname' => 'Hidden Visitor',
        ]);

        $this->createNetworkingEventMaster(
            'ネットワーキングイベントFuture',
            'Networking Event Future',
            'Networking Event Future (Current)',
            [
                'event_date' => '2026-04-20',
                'matching_display_time' => '14:15:00',
            ]
        );
        $this->createNetworkingEventMaster(
            'ネットワーキングイベントPast',
            'Networking Event Past',
            'Networking Event Past (Current)',
            [
                'event_date' => '2026-04-20',
                'matching_display_time' => '13:15:00',
            ]
        );

        $this->createCheckinHistory($actor->user_id, 'Networking Event Future (Current)');
        $this->createCheckinHistory($actor->user_id, 'Networking Event Past (Current)');
        $this->createCheckinHistory($visibleCandidate->user_id, 'Networking Event Past (Current)');
        $this->createCheckinHistory($hiddenCandidate->user_id, 'Networking Event Future (Current)');

        $response = $this->withHeaders([
            'user-uuid' => $actor->uuid,
            'language' => 'eng',
        ])->getJson('/api/v1/matching-partners');

        $response->assertOk()
            ->assertJsonCount(1, 'result.0.list')
            ->assertJsonPath('result.0.list.0.checkin_app_user_name', 'Networking Event Past')
            ->assertJsonCount(1, 'result.0.list.0.items')
            ->assertJsonFragment([
                'nickname' => 'Visible Visitor',
            ])
            ->assertJsonMissing([
                'nickname' => 'Hidden Visitor',
            ]);

        CarbonImmutable::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProfile(array $overrides = []): LiveChatProfiles
    {
        return LiveChatProfiles::query()->create(array_merge([
            'profile_id' => 9001,
            'user_id' => 9001,
            'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'live_chat_data_source_id' => 1,
            'nickname' => 'Test User',
            'company' => 'Sushi Tech',
            'custom_fields' => ['information' => 'hello'],
            'created_at' => '2026-04-20 10:00:00',
            'updated_at' => '2026-04-20 10:00:00',
        ], $overrides));
    }

    private function createCheckinHistory(int $userId, string $checkinAppUserName): void
    {
        CheckinHistory::query()->create([
            'user_id' => $userId,
            'event_id' => 1,
            'checkin_app_user_name' => $checkinAppUserName,
            'is_confirm' => true,
            'created_at' => '2026-04-20 10:00:00',
            'updated_at' => '2026-04-20 10:00:00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createNetworkingEventMaster(
        string $eventNameJa,
        string $eventNameEn,
        string $checkinAppUserName,
        array $overrides = []
    ): void {
        $suffix = substr(str_replace([' ', '(', ')'], '', $checkinAppUserName), 0, 20);

        NetworkingEventMaster::query()->create(array_merge([
            'event_name_ja' => $eventNameJa,
            'event_name_en' => $eventNameEn,
            'expected_participants' => 15,
            'event_date' => '2026-04-27',
            'start_time' => '11:00:00',
            'end_time' => '11:45:00',
            'matching_display_time' => '11:15:00',
            'reception_terminal_id_stg' => sprintf('aaaaaaaa-aaaa-4aaa-8aaa-%012s', substr(md5($suffix.'stg'), 0, 12)),
            'reception_terminal_id_prod' => sprintf('bbbbbbbb-bbbb-4bbb-8bbb-%012s', substr(md5($suffix.'prod'), 0, 12)),
            'checkin_app_user_name' => $checkinAppUserName,
            'created_at' => '2026-04-20 10:00:00',
            'updated_at' => '2026-04-20 10:00:00',
        ], $overrides));
    }
}
