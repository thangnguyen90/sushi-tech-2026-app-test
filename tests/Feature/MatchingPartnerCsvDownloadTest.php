<?php

namespace Tests\Feature;

use App\Models\ChatProfileContent;
use App\Models\LiveChatProfileFieldOption;
use App\Models\LiveChatProfiles;
use App\Models\MatchingCsvDownloadColumn;
use App\Models\MatchingCsvDownloadSetting;
use App\Models\MatchingUser;
use App\Models\ShareProfileField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatchingPartnerCsvDownloadTest extends TestCase
{
    use RefreshDatabase;

    private const string PARTICIPATION_FIELD_KEY = 'participation_attributes_dynamic_key';

    private const string PR_FIELD_KEY = 'pr_free_text_dynamic_key';

    private const string TARGET_INDUSTRY_FIELD_KEY = 'target_industry_dynamic_key';

    private const string WHAT_I_AM_LOOKING_FOR_FIELD_KEY = 'what_i_am_looking_for_dynamic_key';

    private const string WHAT_I_AM_LOOKING_FOR_FREE_TEXT_FIELD_KEY = 'what_i_am_looking_for_free_text_dynamic_key';

    private const string INVALID_OPTION_FIELD_KEY = 'invalid_option_dynamic_key';

    public function test_it_returns_requested_live_chat_users_when_live_chat_user_uuids_are_provided(): void
    {
        $this->createCsvDownloadSetting(true, 'shared-secret-key');
        $this->remapColumnFieldKey('pr_free_text', self::PR_FIELD_KEY);

        $sourceProfile = $this->createProfile([
            'profile_id' => 5001,
            'user_id' => 1001,
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'nickname' => 'Source User',
            'company' => 'Source Company',
            'user_name' => 'Source User',
            'user_email' => 'source@example.com',
            'user_company' => 'Source Inc.',
            'participation_attributes' => '来場者（VIP）',
            'is_exhibitor' => false,
            'custom_fields' => ['seed' => 'source'],
        ]);
        $includedProfile = $this->createProfile([
            'profile_id' => 5002,
            'user_id' => 2001,
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'nickname' => 'Partner One',
            'company' => 'Partner One Company',
            'user_name' => "Taro\nYamada",
            'user_email' => 'taro@example.com',
            'user_company' => 'Sample Inc.',
            'is_exhibitor' => true,
            'custom_fields' => [
                self::PARTICIPATION_FIELD_KEY => 'option-startup',
                self::PR_FIELD_KEY => 'Strong business partner',
            ],
        ]);
        $ignoredMatchingUserProfile = $this->createProfile([
            'profile_id' => 5003,
            'user_id' => 2002,
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'nickname' => 'Partner Two',
            'company' => 'Partner Two Company',
            'user_name' => 'Should Be Included',
            'user_email' => 'included@example.com',
            'user_company' => 'Included Inc.',
            'is_exhibitor' => true,
            'custom_fields' => [
                self::PARTICIPATION_FIELD_KEY => 'option-startup',
                self::PR_FIELD_KEY => 'Still exported',
            ],
        ]);

        $this->createChatProfileContent(
            self::PARTICIPATION_FIELD_KEY,
            'option-startup',
            '参加属性',
            'Participation Attributes',
            'スタートアップ',
            'Startup',
            8
        );
        $this->createChatProfileContent(
            self::PR_FIELD_KEY,
            '__free_text__',
            '自己PR（自由記述）',
            'PR（Free-text）',
            '自己PR（自由記述）',
            'PR（Free-text）',
            99
        );

        $this->createProfileFieldOption($includedProfile->profile_id, self::PARTICIPATION_FIELD_KEY, 'option-startup');
        $this->createProfileFieldOption($includedProfile->profile_id, self::PR_FIELD_KEY, 'Strong business partner');
        $this->createProfileFieldOption($ignoredMatchingUserProfile->profile_id, self::PARTICIPATION_FIELD_KEY, 'option-startup');
        $this->createProfileFieldOption($ignoredMatchingUserProfile->profile_id, self::PR_FIELD_KEY, 'Still exported');

        MatchingUser::query()->create([
            'event_id' => (int) config('eventos.event'),
            'owner_user_id' => $sourceProfile->user_id,
            'peer_user_id' => $ignoredMatchingUserProfile->user_id,
            'owner_uuid' => $sourceProfile->uuid,
            'peer_uuid' => $ignoredMatchingUserProfile->uuid,
            'status' => 1,
            'appointment_status' => null,
            'created_at' => '2026-04-20 10:00:00',
            'updated_at' => '2026-04-20 10:00:00',
        ]);
        MatchingUser::query()->create([
            'event_id' => (int) config('eventos.event'),
            'owner_user_id' => $sourceProfile->user_id,
            'peer_user_id' => $includedProfile->user_id,
            'owner_uuid' => $sourceProfile->uuid,
            'peer_uuid' => $includedProfile->uuid,
            'status' => 1,
            'appointment_status' => null,
            'created_at' => '2026-04-20 10:01:00',
            'updated_at' => '2026-04-20 10:01:00',
        ]);

        $response = $this->withHeaders($this->csvDownloadHeaders())->postJson('/api/v1/matching/csv-download', [
            'uuid' => $sourceProfile->uuid,
            'live_chat_user_uuids' => [$sourceProfile->uuid],
            'language' => 'jpn',
        ]);

        $response->assertOk()
            ->assertJsonPath('code', 'OK')
            ->assertJsonPath('result.headers.0', '名前')
            ->assertJsonPath('result.headers.1', '会社名')
            ->assertJsonPath('result.headers.2', 'メールアドレス')
            ->assertJsonPath('result.headers.3', '参加属性')
            ->assertJsonPath('result.headers.4', '来場者判定')
            ->assertJsonPath('result.users.0.3', '来場者（VIP）')
            ->assertJsonPath('result.users.0.4', '来場者')
            ->assertJsonCount(1, 'result.users');

        $response->assertJsonFragment(['Source User']);
        $response->assertJsonFragment(['Source Inc.']);
        $response->assertJsonFragment(['source@example.com']);
        $response->assertJsonMissing(['出展者']);
        $response->assertJsonMissing(['Taro Yamada']);
        $response->assertJsonMissing(['Should Be Included']);
    }

    public function test_it_returns_validation_error_when_live_chat_user_uuids_is_not_provided(): void
    {
        $this->createCsvDownloadSetting(true, 'shared-secret-key');

        $response = $this->withHeaders($this->csvDownloadHeaders())->postJson('/api/v1/matching/csv-download', [
            'uuid' => 'abababab-abab-4aba-8aba-abababababab',
            'language' => 'eng',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('result.message', 'live_chat_user_uuids is required.');
    }

    public function test_it_returns_validation_error_when_live_chat_user_uuids_is_not_provided_even_if_uuid_exists(): void
    {
        $this->createCsvDownloadSetting(true, 'shared-secret-key');

        $includedProfile = $this->createProfile([
            'profile_id' => 6502,
            'user_id' => 2151,
            'uuid' => 'cdcdcdcd-cdcd-4cdc-8cdc-cdcdcdcdcdcd',
            'nickname' => 'Partner One',
            'company' => 'Partner One Company',
            'user_name' => 'User One',
            'user_email' => 'one@example.com',
            'user_company' => 'One Inc.',
            'is_exhibitor' => true,
            'custom_fields' => [self::PARTICIPATION_FIELD_KEY => 'option-startup'],
        ]);

        $response = $this->withHeaders([
            'language' => 'eng',
            ...$this->csvDownloadHeaders(),
        ])->postJson('/api/v1/matching/csv-download', [
            'uuid' => $includedProfile->uuid,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('result.message', 'live_chat_user_uuids is required.');
    }

    public function test_it_returns_participation_attributes_from_db_column_when_language_is_eng(): void
    {
        $this->createCsvDownloadSetting(true, 'shared-secret-key');
        $this->createShareProfileContent(
            14635,
            2,
            'Participation Attributes',
            [
                [
                    'key' => '2745b6e8-faac-4d83-b465-ff1cf5826e11',
                    'value' => 'Government',
                ],
            ]
        );

        $visitorProfile = $this->createProfile([
            'profile_id' => 6601,
            'user_id' => 2161,
            'uuid' => 'edededed-eded-4ede-8ede-edededededed',
            'nickname' => 'Visitor User',
            'company' => 'Visitor Company',
            'user_name' => 'Visitor User',
            'user_email' => 'visitor@example.com',
            'user_company' => 'Visitor Inc.',
            'participation_attributes' => '2745b6e8-faac-4d83-b465-ff1cf5826e11',
            'is_exhibitor' => false,
            'custom_fields' => ['seed' => 'visitor'],
        ]);

        $response = $this->withHeaders($this->csvDownloadHeaders())->postJson('/api/v1/matching/csv-download', [
            'uuid' => $visitorProfile->uuid,
            'live_chat_user_uuids' => [$visitorProfile->uuid],
            'language' => 'eng',
        ]);

        $response->assertOk()
            ->assertJsonPath('result.headers.3', 'Participation Attributes')
            ->assertJsonPath('result.headers.4', 'Attendee Category')
            ->assertJsonPath('result.users.0.3', 'Government')
            ->assertJsonPath('result.users.0.4', 'Visitor');
    }

    public function test_it_returns_participation_attributes_from_share_profile_contents_when_language_is_jpn(): void
    {
        $this->createCsvDownloadSetting(true, 'shared-secret-key');
        $this->createShareProfileContent(
            14635,
            1,
            '参加属性',
            [
                [
                    'key' => '7dcfb0ba-4bc4-4692-8035-0e900b009465',
                    'value' => 'スタートアップ',
                ],
            ]
        );

        $visitorProfile = $this->createProfile([
            'profile_id' => 6602,
            'user_id' => 2162,
            'uuid' => 'ababeded-eded-4ede-8ede-edededededed',
            'nickname' => 'Visitor User JPN',
            'company' => 'Visitor Company JPN',
            'user_name' => 'Visitor User JPN',
            'user_email' => 'visitor-jpn@example.com',
            'user_company' => 'Visitor JPN Inc.',
            'participation_attributes' => '7dcfb0ba-4bc4-4692-8035-0e900b009465',
            'is_exhibitor' => false,
            'custom_fields' => ['seed' => 'visitor-jpn'],
        ]);

        $response = $this->withHeaders($this->csvDownloadHeaders())->postJson('/api/v1/matching/csv-download', [
            'uuid' => $visitorProfile->uuid,
            'live_chat_user_uuids' => [$visitorProfile->uuid],
            'language' => 'jpn',
        ]);

        $response->assertOk()
            ->assertJsonPath('result.headers.3', '参加属性')
            ->assertJsonPath('result.headers.4', '来場者判定')
            ->assertJsonPath('result.users.0.3', 'スタートアップ')
            ->assertJsonPath('result.users.0.4', '来場者');
    }

    public function test_it_returns_exhibitor_attendee_category_when_profile_is_exhibitor(): void
    {
        $this->createCsvDownloadSetting(true, 'shared-secret-key');
        $this->createShareProfileContent(
            18544,
            2,
            'Entry Pass Fields',
            [
                [
                    'key' => 'ff5d19b0-245a-4bc0-852b-b4238d344f09',
                    'value' => 'Exhibitor',
                ],
                [
                    'key' => '55e90b21-89e4-41ae-9731-70a42bc06cb7',
                    'value' => 'Visitor',
                ],
            ]
        );

        $exhibitorProfile = $this->createProfile([
            'profile_id' => 6603,
            'user_id' => 2163,
            'uuid' => 'cececece-eded-4ede-8ede-edededededed',
            'nickname' => 'Exhibitor User',
            'company' => 'Exhibitor Company',
            'user_name' => 'Exhibitor User',
            'user_email' => 'exhibitor@example.com',
            'user_company' => 'Exhibitor Inc.',
            'is_exhibitor' => true,
            'attendee_category' => 'ff5d19b0-245a-4bc0-852b-b4238d344f09',
            'custom_fields' => ['seed' => 'exhibitor'],
        ]);

        $response = $this->withHeaders($this->csvDownloadHeaders())->postJson('/api/v1/matching/csv-download', [
            'uuid' => $exhibitorProfile->uuid,
            'live_chat_user_uuids' => [$exhibitorProfile->uuid],
            'language' => 'eng',
        ]);

        $response->assertOk()
            ->assertJsonPath('result.headers.4', 'Attendee Category')
            ->assertJsonPath('result.users.0.4', 'Exhibitor');
    }

    public function test_it_returns_empty_payload_when_csv_download_setting_is_disabled(): void
    {
        $this->createCsvDownloadSetting(false, 'shared-secret-key');

        $this->createProfile([
            'profile_id' => 7002,
            'user_id' => 2201,
            'uuid' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'nickname' => 'Partner One',
            'company' => 'Partner One Company',
            'user_name' => 'User One',
            'user_email' => 'one@example.com',
            'user_company' => 'One Inc.',
            'custom_fields' => ['seed' => 'one'],
        ]);

        $response = $this->withHeaders($this->csvDownloadHeaders())->postJson('/api/v1/matching/csv-download', [
            'uuid' => 'ffffffff-ffff-4fff-8fff-ffffffffffff',
            'live_chat_user_uuids' => ['ffffffff-ffff-4fff-8fff-ffffffffffff'],
            'language' => 'eng',
        ]);

        $response->assertOk()
            ->assertJsonPath('result', []);
    }

    public function test_it_returns_only_free_text_columns_directly_from_custom_fields_when_values_are_not_additional_keys(): void
    {
        $this->createCsvDownloadSetting(true, 'shared-secret-key');
        $this->remapColumnFieldKey('pr_free_text', self::PR_FIELD_KEY);
        $this->remapColumnFieldKey('target_industry', self::TARGET_INDUSTRY_FIELD_KEY);
        $this->remapColumnFieldKey('what_i_am_looking_for', self::WHAT_I_AM_LOOKING_FOR_FIELD_KEY);
        $this->remapColumnFieldKey('what_i_am_looking_for_free_text', self::WHAT_I_AM_LOOKING_FOR_FREE_TEXT_FIELD_KEY);

        $profile = $this->createProfile([
            'profile_id' => 7051,
            'user_id' => 2205,
            'uuid' => 'abababab-abab-4aba-8aba-bbbbbbbbbbbb',
            'nickname' => 'PR Fallback User',
            'company' => 'Fallback Company',
            'user_name' => 'PR Fallback User',
            'user_email' => 'pr-fallback@example.com',
            'user_company' => 'Fallback Inc.',
            'custom_fields' => [
                self::PR_FIELD_KEY => 'Pulled directly from custom_fields',
                self::TARGET_INDUSTRY_FIELD_KEY => 'option1770883780896456',
                self::WHAT_I_AM_LOOKING_FOR_FIELD_KEY => 'option1770883887246478',
                self::WHAT_I_AM_LOOKING_FOR_FREE_TEXT_FIELD_KEY => 'Looking for long-term collaboration',
            ],
        ]);

        $this->createProfileFieldOption($profile->profile_id, self::PR_FIELD_KEY, 'Value from live_chat_profile_field_options');
        $this->createProfileFieldOption($profile->profile_id, self::TARGET_INDUSTRY_FIELD_KEY, 'Another industry from options');
        $this->createProfileFieldOption($profile->profile_id, self::WHAT_I_AM_LOOKING_FOR_FIELD_KEY, 'Another expectation from options');
        $this->createProfileFieldOption($profile->profile_id, self::WHAT_I_AM_LOOKING_FOR_FREE_TEXT_FIELD_KEY, 'Another free text from options');

        $response = $this->withHeaders($this->csvDownloadHeaders())->postJson('/api/v1/matching/csv-download', [
            'uuid' => $profile->uuid,
            'live_chat_user_uuids' => [$profile->uuid],
            'language' => 'jpn',
        ]);

        $response->assertOk()
            ->assertJsonPath('result.headers.14', '自己PR')
            ->assertJsonPath('result.headers.15', 'マッチング相手の希望業種')
            ->assertJsonPath('result.headers.16', '相手に期待すること')
            ->assertJsonPath('result.headers.17', '相手に期待すること（自由記述）')
            ->assertJsonPath('result.users.0.14', 'Pulled directly from custom_fields')
            ->assertJsonPath('result.users.0.15', '')
            ->assertJsonPath('result.users.0.16', '')
            ->assertJsonPath('result.users.0.17', 'Looking for long-term collaboration');
    }

    public function test_it_falls_back_to_existing_logic_when_custom_fields_value_is_additional_key(): void
    {
        $this->createCsvDownloadSetting(true, 'shared-secret-key');
        $this->remapColumnFieldKey('target_industry', self::TARGET_INDUSTRY_FIELD_KEY);

        $profile = $this->createProfile([
            'profile_id' => 7052,
            'user_id' => 2206,
            'uuid' => 'bcbcbcbc-bcbc-4bcb-8bcb-bcbcbcbcbcbc',
            'nickname' => 'Additional Fallback User',
            'company' => 'Fallback Company',
            'user_name' => 'Additional Fallback User',
            'user_email' => 'additional-fallback@example.com',
            'user_company' => 'Fallback Inc.',
            'custom_fields' => [
                self::TARGET_INDUSTRY_FIELD_KEY => 'additional1770883225643333',
            ],
        ]);

        $this->createChatProfileContent(
            self::TARGET_INDUSTRY_FIELD_KEY,
            'option1770883780896456',
            'マッチング相手の希望業種',
            'Target Industry',
            'Mapped from existing logic',
            'Mapped from existing logic',
            100
        );

        $this->createProfileFieldOption($profile->profile_id, self::TARGET_INDUSTRY_FIELD_KEY, 'option1770883780896456');

        $response = $this->withHeaders($this->csvDownloadHeaders())->postJson('/api/v1/matching/csv-download', [
            'uuid' => $profile->uuid,
            'live_chat_user_uuids' => [$profile->uuid],
            'language' => 'jpn',
        ]);

        $response->assertOk()
            ->assertJsonPath('result.users.0.15', 'Mapped from existing logic');
    }

    public function test_it_returns_empty_string_when_custom_option_label_is_not_found(): void
    {
        $this->createCsvDownloadSetting(true, 'shared-secret-key');
        $this->remapColumnFieldKey('investable_amount', self::INVALID_OPTION_FIELD_KEY);

        $profile = $this->createProfile([
            'profile_id' => 7101,
            'user_id' => 2211,
            'uuid' => 'f1f1f1f1-f1f1-41f1-81f1-f1f1f1f1f1f1',
            'nickname' => 'Invalid Option User',
            'company' => 'Invalid Option Company',
            'user_name' => 'Invalid Option User',
            'user_email' => 'invalid-option@example.com',
            'user_company' => 'Invalid Option Inc.',
            'custom_fields' => ['seed' => 'value'],
        ]);

        $this->createProfileFieldOption($profile->profile_id, self::INVALID_OPTION_FIELD_KEY, 'option1770883682963442');

        $response = $this->withHeaders($this->csvDownloadHeaders())->postJson('/api/v1/matching/csv-download', [
            'uuid' => $profile->uuid,
            'live_chat_user_uuids' => [$profile->uuid],
            'language' => 'jpn',
        ]);

        $response->assertOk()
            ->assertJsonPath('result.headers.12', '投資可能額')
            ->assertJsonPath('result.users.0.12', '');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProfile(array $overrides = []): LiveChatProfiles
    {
        return LiveChatProfiles::query()->create(array_merge([
            'profile_id' => random_int(10000, 99999),
            'user_id' => random_int(1000, 9999),
            'uuid' => (string) Str::uuid(),
            'live_chat_data_source_id' => (int) config('eventos.live_chat_data_source_id'),
            'last_event_id' => (int) config('eventos.event'),
            'nickname' => 'Test User',
            'company' => 'Test Company',
            'mail_address' => 'test@example.com',
            'custom_fields' => ['seed' => 'value'],
            'created_at' => '2026-04-20 10:00:00',
            'updated_at' => '2026-04-20 10:00:00',
        ], $overrides));
    }

    private function createChatProfileContent(
        string $fieldKey,
        string $optionValue,
        string $labelJpn,
        string $labelEng,
        string $optionLabelJpn,
        string $optionLabelEng,
        int $sortOrder
    ): void {
        ChatProfileContent::query()->create([
            'field_key' => $fieldKey,
            'option_value' => $optionValue,
            'label_jpn' => $optionLabelJpn,
            'label_eng' => $optionLabelEng,
            'language_setting' => [
                'jpn' => ['label' => $labelJpn, 'description' => ''],
                'eng' => ['label' => $labelEng, 'description' => ''],
            ],
            'sort_order' => $sortOrder,
            'is_enabled' => true,
        ]);
    }

    private function createProfileFieldOption(int $profileId, string $fieldKey, string $optionValue): void
    {
        LiveChatProfileFieldOption::query()->create([
            'profile_id' => $profileId,
            'field_key' => $fieldKey,
            'option_value' => $optionValue,
            'created_at' => '2026-04-20 10:00:00',
            'updated_at' => '2026-04-20 10:00:00',
        ]);
    }

    /**
     * @param  array<int, array{key: string, value: string}>  $selectorItems
     */
    private function createShareProfileContent(
        int $shareProfileId,
        int $languageId,
        string $label,
        array $selectorItems
    ): void {
        ShareProfileField::query()->create([
            'share_profile_id' => $shareProfileId,
            'language_id' => $languageId,
            'is_enabled' => true,
            'is_required' => true,
            'is_uneditable' => false,
            'is_hidden' => false,
            'is_default' => false,
            'label' => $label,
            'entry_form_key' => '2c0e209c-f167-4906-85fd-51204922e7dc',
            'answer_method' => 'SELECT',
            'priority' => 11,
            'setting' => ['place_holder' => ''],
            'selector_items' => $selectorItems,
            'created_at' => '2026-04-20 10:00:00',
            'updated_at' => '2026-04-20 10:00:00',
        ]);
    }

    private function createCsvDownloadSetting(bool $isEnabled, ?string $encryptionKey): MatchingCsvDownloadSetting
    {
        return MatchingCsvDownloadSetting::query()->create([
            'encryption_key' => $encryptionKey,
            'is_enabled' => $isEnabled,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function csvDownloadHeaders(): array
    {
        return [
            'x-encryption-key' => 'shared-secret-key',
        ];
    }

    private function remapColumnFieldKey(string $columnKey, string $fieldKey): void
    {
        MatchingCsvDownloadColumn::query()
            ->where('column_key', $columnKey)
            ->update(['custom_field_key' => $fieldKey]);
    }
}
