<?php

namespace Tests\Feature;

use App\Models\LiveChatProfiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportLiveChatProfileCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const array CSV_HEADERS = [
        'user_uuid',
        'id',
        'user_id',
        'live_chat_data_source_id',
        'live_chat_user_id',
        'uuid',
        'nickname',
        'icon_image',
        'background_image',
        'introduction',
        'mail_notification',
        'mail_address',
        'company',
        'custom_fields',
        'display_is_search',
        'exhibitor_administrator_id',
        'last_portal_id',
        'last_event_id',
        'created_at',
        'updated_at',
        'exhibitor_text',
        'email',
        'name',
        'company_name',
        'participation_attribute_value',
        'attendee_category',
    ];

    /**
     * @var list<string>
     */
    private const array EXPORTED_CSV_HEADERS = [
        'id',
        'user_id',
        'updated_at',
        'created_at',
        'user_uuid',
        'live_chat_data_source_id',
        'live_chat_user_id',
        'uuid',
        'nickname',
        'icon_image',
        'background_image',
        'introduction',
        'mail_notification',
        'mail_address',
        'company',
        'custom_fields',
        'display_is_search',
        'exhibitor_administrator_id',
        'last_portal_id',
        'last_event_id',
        'exhibitor_text',
        'email',
        'name',
        'company_name',
        'participation_attribute_value',
        'attendee_category',
    ];

    public function test_it_imports_user_identity_columns_from_csv(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put(
            'imports/live_chat_profiles.csv.gz',
            gzencode($this->csvContent([
                [
                    'id' => '7001',
                    'user_id' => '1001',
                    'user_uuid' => '11111111-1111-4111-8111-111111111111',
                    'live_chat_data_source_id' => '1450',
                    'live_chat_user_id' => 'live-chat-user-1001',
                    'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                    'nickname' => 'Profile Nickname',
                    'icon_image' => '',
                    'background_image' => '',
                    'introduction' => 'Hello world',
                    'mail_notification' => 'false',
                    'mail_address' => 'profile@example.com',
                    'company' => 'Profile Company',
                    'custom_fields' => '{"field":"value"}',
                    'display_is_search' => 'false',
                    'exhibitor_administrator_id' => '',
                    'last_portal_id' => '749',
                    'last_event_id' => '15781',
                    'created_at' => '2026-4-14, 11:11',
                    'updated_at' => '2026-4-21, 17:08',
                    'exhibitor_text' => '出展者',
                    'email' => 'user@example.com',
                    'name' => 'Visitor Name',
                    'company_name' => 'Visitor Company',
                    'participation_attribute_value' => 'スタートアップ',
                    'attendee_category' => '出展者',
                ],
            ]))
        );

        $this->artisan('live_chat_profiles', [
            'file' => 'imports/live_chat_profiles.csv.gz',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('live_chat_profiles', [
            'profile_id' => 7001,
            'user_id' => 1001,
            'uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'is_exhibitor' => true,
            'user_name' => 'Visitor Name',
            'user_email' => 'user@example.com',
            'user_company' => 'Visitor Company',
            'participation_attributes' => 'スタートアップ',
            'attendee_category' => '出展者',
        ]);

        $profile = LiveChatProfiles::query()->where('profile_id', 7001)->firstOrFail();

        $this->assertSame('Visitor Name', $profile->user_name);
        $this->assertSame('user@example.com', $profile->user_email);
        $this->assertSame('Visitor Company', $profile->user_company);
        $this->assertSame('スタートアップ', $profile->participation_attributes);
        $this->assertSame('出展者', $profile->attendee_category);
    }

    public function test_it_repairs_malformed_custom_fields_without_shifting_user_identity_columns(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put(
            'imports/live_chat_profiles.csv.gz',
            gzencode($this->malformedCsvContent())
        );

        $this->artisan('live_chat_profiles', [
            'file' => 'imports/live_chat_profiles.csv.gz',
        ])->assertExitCode(0);

        $profile = LiveChatProfiles::query()->where('profile_id', 318412)->firstOrFail();

        $this->assertSame("Assawagetmaneee\nMaturada", $profile->user_name);
        $this->assertSame('maturada.assawagetmanee@rhinoflux.com', $profile->user_email);
        $this->assertSame('ライノフラックス株式会社', $profile->user_company);
        $this->assertSame('Rhinoflux Inc.', $profile->company);
        $this->assertIsArray($profile->custom_fields);
        $this->assertSame(
            'https://rhinoflux.com/',
            $profile->custom_fields['additional1772021972575298'] ?? null
        );
    }

    public function test_it_imports_participation_attribute_uuid_into_live_chat_profiles(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put(
            'imports/live_chat_profiles.csv.gz',
            gzencode($this->csvContent([
                [
                    'id' => '7002',
                    'user_id' => '1002',
                    'user_uuid' => '22222222-2222-4222-8222-222222222222',
                    'live_chat_data_source_id' => '1450',
                    'live_chat_user_id' => 'live-chat-user-1002',
                    'uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                    'nickname' => 'Profile Nickname 2',
                    'icon_image' => '',
                    'background_image' => '',
                    'introduction' => 'Hello world 2',
                    'mail_notification' => 'false',
                    'mail_address' => 'profile2@example.com',
                    'company' => 'Profile Company 2',
                    'custom_fields' => '{"field":"value"}',
                    'display_is_search' => 'false',
                    'exhibitor_administrator_id' => '',
                    'last_portal_id' => '749',
                    'last_event_id' => '15781',
                    'created_at' => '2026-4-14, 11:11',
                    'updated_at' => '2026-4-21, 17:08',
                    'exhibitor_text' => '',
                    'email' => 'user2@example.com',
                    'name' => 'Visitor Name 2',
                    'company_name' => 'Visitor Company 2',
                    'participation_attribute_value' => '1e3e9669-9d5a-46c2-bd9c-7a0f80677594',
                    'attendee_category' => '来場者',
                ],
            ]))
        );

        $this->artisan('live_chat_profiles', [
            'file' => 'imports/live_chat_profiles.csv.gz',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('live_chat_profiles', [
            'profile_id' => 7002,
            'participation_attributes' => '1e3e9669-9d5a-46c2-bd9c-7a0f80677594',
            'attendee_category' => '来場者',
        ]);
    }

    public function test_it_imports_attendee_category_from_local_csv_file_path(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'live-chat-profiles-');
        $csvPath = $tempFile.'.csv';

        rename($tempFile, $csvPath);
        file_put_contents($csvPath, $this->csvContentWithHeaders(self::EXPORTED_CSV_HEADERS, [
            [
                'id' => '7003',
                'user_id' => '1003',
                'user_uuid' => '33333333-3333-4333-8333-333333333333',
                'live_chat_data_source_id' => '1450',
                'live_chat_user_id' => 'live-chat-user-1003',
                'uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
                'nickname' => 'Profile Nickname 3',
                'icon_image' => '',
                'background_image' => '',
                'introduction' => 'Hello world 3',
                'mail_notification' => 'false',
                'mail_address' => 'profile3@example.com',
                'company' => 'Profile Company 3',
                'custom_fields' => '{"field":"value"}',
                'display_is_search' => 'false',
                'exhibitor_administrator_id' => '',
                'last_portal_id' => '749',
                'last_event_id' => '15781',
                'created_at' => '2026-4-14, 11:11',
                'updated_at' => '2026-4-21, 17:08',
                'exhibitor_text' => '',
                'email' => 'user3@example.com',
                'name' => 'Visitor Name 3',
                'company_name' => 'Visitor Company 3',
                'participation_attribute_value' => 'Government',
                'attendee_category' => '来場者',
            ],
        ]));

        try {
            $this->artisan('live_chat_profiles', [
                'file' => $csvPath,
            ])->assertExitCode(0);

            $this->assertDatabaseHas('live_chat_profiles', [
                'profile_id' => 7003,
                'participation_attributes' => 'Government',
                'attendee_category' => '来場者',
            ]);
        } finally {
            @unlink($csvPath);
        }
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function csvContent(array $rows): string
    {
        return $this->csvContentWithHeaders(self::CSV_HEADERS, $rows);
    }

    /**
     * @param  list<string>  $headers
     * @param  array<int, array<string, string>>  $rows
     */
    private function csvContentWithHeaders(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            $fields = array_map(
                static fn (string $header): string => $row[$header] ?? '',
                $headers
            );

            fputcsv($stream, $fields, ',', '"', '\\');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    private function malformedCsvContent(): string
    {
        return implode(',', self::CSV_HEADERS)."\n"
            .'2c689152-a3ba-482b-bb9b-6025f07e214d,318412,7994172,1455,PDRl9uOOVPfvRQ9SOPbSAgpLiQFHxlM73OZD,17d5d7e5-e4a1-43c0-9344-2cb6cae9ea97,Assawagetmaneee Maturada,null,null,,false,maturada.assawagetmanee@rhinoflux.com,Rhinoflux Inc.,"{""additional17720140841061"": ""option177305650780551"", ""additional17720140860572"": ""option177305656169372"", ""additional17720140871183"": ""option1773056787528146"", ""additional17720140879634"": ""option1773056746448133"", ""additional17720140887955"": """", ""additional17730560193861"": ""option1773058431497381"", ""additional177305609280235"": ""option1773058570907403"", ""additional1772021394036178"": """", ""additional1772021437708195"": """", ""additional1772021532391218"": ""Rhinoflux Inc. is a Kyoto University-based startup developing a revolutionary non-combustion biomass power generation technology by using \""Hydro Chemical Looping\"" method to utilize an aqueous solution to convert biomass into electricity at low temperatures, achieving up to four times the efficiency of traditional thermal power. \n\nThis innovative process bypasses the need for costly drying, allowing for the direct use of high-moisture waste from biomass. \n\nBeyond clean energy, the system simultaneously captures high-purity CO₂ providing customers with an additional revenue stream while reducing waste disposal costs."", ""additional1772021554857219"": ""option177315936077259"", ""additional1772021628631266"": ""option1773057041653224"", ""additional1772021679661297"": """", ""additional1772021972575298"": ""https://rhinoflux.com/""}",false,,,14943,"2026-4-14, 11:11","2026-4-21, 17:08",出展者,maturada.assawagetmanee@rhinoflux.com,"Assawagetmaneee'."\n"
            .'Maturada'."\n\n"
            .'",ライノフラックス株式会社,,';
    }
}
