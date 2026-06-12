<?php

namespace Tests\Feature;

use App\Models\MatchingUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportMatchingUsersBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_missing_matching_users_appointment_data_from_local_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/matching_users_backfill.csv', $this->csvContent([
            [
                'id' => '32395',
                'event_id' => '14943',
                'schedule_start_datetime' => '2026-4-27, 09:30',
                'applicant_visitor_id' => '7671402',
                'applicant_exhibitor_administrator_id' => '',
                'recipient_visitor_id' => '7892390',
                'recipient_exhibitor_administrator_id' => '',
                'applicant_name' => 'Okada Yuki',
                'recipient_name' => 'Kim Jin',
                'applicant_email' => 'yuki_4.okada@toppan.co.jp',
                'recipient_email' => 'kimjin7167@gbmb2b.com',
                'applicant_company_name' => 'TOPPAN Inc.',
                'recipient_company_name' => 'Seoul',
                'applicant_chat_uuid' => '3e13b8b9-7573-4949-a638-cf4c83dc2d44',
                'recipient_chat_uuid' => '99acbc2b-c955-4922-9ad6-84c0c092f6c3',
                'application_source' => 'Chat',
                'exhibitor_administrator_appointment_schedule_id' => '',
                'user_id' => '7671402',
                'booth_id' => '',
                'language_id' => '1',
                'status' => 'Approved',
                'exhibitor_recipient_memo' => '',
                'exhibitor_applicant_memo' => '',
                'approval_message' => 'Welcome',
                'cancel_message' => '',
                'user_name' => 'Okada Yuki',
                'user_company' => 'TOPPAN Inc.',
                'user_mail_address' => 'yuki_4.okada@toppan.co.jp',
                'user_message' => '',
                'user_facehub_direct_meeting_room_id' => '',
                'exhibitor_administrator_facehub_url' => '',
                'user_facehub_url' => '',
                'is_approval_send_mail' => 'false',
                'is_cancel_send_mail' => 'false',
                'is_add_ics' => 'false',
                'approval_message_language_id' => '1',
                'cancel_message_language_id' => '1',
                'business_appointment_room_id' => '48',
                'meeting_location' => '',
                'is_change_meeting_location' => '0',
                'approval_message_cc' => '',
                'cancel_message_cc' => '',
                'approval_message_bcc' => '',
                'cancel_message_bcc' => '',
                'created_at' => '2026-4-7, 18:02',
                'updated_at' => '2026-4-14, 11:13',
            ],
        ]));

        MatchingUser::query()->create([
            'event_id' => 14943,
            'owner_user_id' => 7671402,
            'owner_uuid' => 'old-owner-uuid',
            'peer_user_id' => 7892390,
            'peer_uuid' => 'old-peer-uuid',
            'status' => 1,
            'appointment_schedule_id' => null,
            'business_appointment_room_id' => null,
            'schedule_start_datetime' => null,
            'appointment_status' => null,
            'webhook_module_code' => null,
            'webhook_schedule_status' => null,
            'webhook_data' => null,
        ]);

        $this->artisan('exhibitor_administrator_appointment_schedule_details', [
            'file' => 'imports/matching_users_backfill.csv',
            '--disk' => 'local',
            '--chunk' => 1,
        ])->assertExitCode(0);

        $this->artisan('exhibitor_administrator_appointment_schedule_details', [
            'file' => 'imports/matching_users_backfill.csv',
            '--disk' => 'local',
            '--chunk' => 1,
        ])->assertExitCode(0);

        $this->assertSame(2, MatchingUser::query()->count());

        $this->assertDatabaseHas('matching_users', [
            'event_id' => 14943,
            'owner_user_id' => 7671402,
            'owner_uuid' => '3e13b8b9-7573-4949-a638-cf4c83dc2d44',
            'peer_user_id' => 7892390,
            'peer_uuid' => '99acbc2b-c955-4922-9ad6-84c0c092f6c3',
            'appointment_schedule_id' => 32395,
            'business_appointment_room_id' => 48,
            'schedule_start_datetime' => '2026-04-27 09:30:00',
            'appointment_status' => 'approved',
            'webhook_module_code' => 'BusinessAppointmentApproved',
            'webhook_schedule_status' => 'Approved',
            'status' => 4,
        ]);

        $this->assertDatabaseHas('matching_users', [
            'event_id' => 14943,
            'owner_user_id' => 7892390,
            'owner_uuid' => '99acbc2b-c955-4922-9ad6-84c0c092f6c3',
            'peer_user_id' => 7671402,
            'peer_uuid' => '3e13b8b9-7573-4949-a638-cf4c83dc2d44',
            'appointment_schedule_id' => 32395,
            'business_appointment_room_id' => 48,
            'schedule_start_datetime' => '2026-04-27 09:30:00',
            'appointment_status' => 'approved',
            'webhook_module_code' => 'BusinessAppointmentApproved',
            'webhook_schedule_status' => 'Approved',
            'status' => 4,
        ]);

        $matchingUser = MatchingUser::query()
            ->where('owner_user_id', 7671402)
            ->where('peer_user_id', 7892390)
            ->firstOrFail();

        $this->assertSame('Okada Yuki', data_get($matchingUser->webhook_data, 'applicant.name'));
        $this->assertSame('Kim Jin', data_get($matchingUser->webhook_data, 'recipient.name'));
        $this->assertSame(
            '2026-04-27 09:30:00',
            data_get($matchingUser->webhook_data, 'exhibitor_administrator_appointment_schedule_detail.schedule_start_datetime')
        );
        $this->assertSame('csv_backfill', data_get($matchingUser->webhook_data, '_meta.source'));
    }

    public function test_it_updates_rows_when_matching_users_already_has_the_same_appointment_schedule_id(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/matching_users_backfill.csv', $this->csvContent([
            [
                'id' => '32395',
                'event_id' => '14943',
                'schedule_start_datetime' => '2026-4-27, 09:30',
                'applicant_visitor_id' => '7671402',
                'applicant_exhibitor_administrator_id' => '',
                'recipient_visitor_id' => '7892390',
                'recipient_exhibitor_administrator_id' => '',
                'applicant_name' => 'Okada Yuki',
                'recipient_name' => 'Kim Jin',
                'applicant_email' => 'yuki_4.okada@toppan.co.jp',
                'recipient_email' => 'kimjin7167@gbmb2b.com',
                'applicant_company_name' => 'TOPPAN Inc.',
                'recipient_company_name' => 'Seoul',
                'applicant_chat_uuid' => '3e13b8b9-7573-4949-a638-cf4c83dc2d44',
                'recipient_chat_uuid' => '99acbc2b-c955-4922-9ad6-84c0c092f6c3',
                'application_source' => 'Chat',
                'exhibitor_administrator_appointment_schedule_id' => '',
                'user_id' => '7671402',
                'booth_id' => '',
                'language_id' => '1',
                'status' => 'Approved',
                'exhibitor_recipient_memo' => '',
                'exhibitor_applicant_memo' => '',
                'approval_message' => 'Welcome',
                'cancel_message' => '',
                'user_name' => 'Okada Yuki',
                'user_company' => 'TOPPAN Inc.',
                'user_mail_address' => 'yuki_4.okada@toppan.co.jp',
                'user_message' => '',
                'user_facehub_direct_meeting_room_id' => '',
                'exhibitor_administrator_facehub_url' => '',
                'user_facehub_url' => '',
                'is_approval_send_mail' => 'false',
                'is_cancel_send_mail' => 'false',
                'is_add_ics' => 'false',
                'approval_message_language_id' => '1',
                'cancel_message_language_id' => '1',
                'business_appointment_room_id' => '48',
                'meeting_location' => '',
                'is_change_meeting_location' => '0',
                'approval_message_cc' => '',
                'cancel_message_cc' => '',
                'approval_message_bcc' => '',
                'cancel_message_bcc' => '',
                'created_at' => '2026-4-7, 18:02',
                'updated_at' => '2026-4-14, 11:13',
            ],
        ]));

        MatchingUser::query()->create([
            'event_id' => 14943,
            'owner_user_id' => 7671402,
            'owner_uuid' => 'existing-owner-uuid',
            'peer_user_id' => 7892390,
            'peer_uuid' => 'existing-peer-uuid',
            'status' => 4,
            'appointment_schedule_id' => 32395,
            'business_appointment_room_id' => 88,
            'schedule_start_datetime' => '2026-05-01 10:00:00',
            'appointment_status' => 'approved',
            'webhook_module_code' => 'BusinessAppointmentApproved',
            'webhook_schedule_status' => 'Approved',
            'webhook_data' => ['module_code' => 'BusinessAppointmentApproved'],
        ]);

        $this->artisan('exhibitor_administrator_appointment_schedule_details', [
            'file' => 'imports/matching_users_backfill.csv',
            '--disk' => 'local',
            '--chunk' => 1,
        ])->assertExitCode(0);

        $this->assertSame(2, MatchingUser::query()->count());
        $this->assertDatabaseHas('matching_users', [
            'event_id' => 14943,
            'owner_user_id' => 7671402,
            'owner_uuid' => '3e13b8b9-7573-4949-a638-cf4c83dc2d44',
            'peer_user_id' => 7892390,
            'peer_uuid' => '99acbc2b-c955-4922-9ad6-84c0c092f6c3',
            'appointment_schedule_id' => 32395,
            'business_appointment_room_id' => 48,
            'schedule_start_datetime' => '2026-04-27 09:30:00',
            'appointment_status' => 'approved',
            'webhook_module_code' => 'BusinessAppointmentApproved',
            'webhook_schedule_status' => 'Approved',
            'status' => 4,
        ]);

        $this->assertDatabaseHas('matching_users', [
            'event_id' => 14943,
            'owner_user_id' => 7892390,
            'owner_uuid' => '99acbc2b-c955-4922-9ad6-84c0c092f6c3',
            'peer_user_id' => 7671402,
            'peer_uuid' => '3e13b8b9-7573-4949-a638-cf4c83dc2d44',
            'appointment_schedule_id' => 32395,
            'business_appointment_room_id' => 48,
            'schedule_start_datetime' => '2026-04-27 09:30:00',
            'appointment_status' => 'approved',
            'webhook_module_code' => 'BusinessAppointmentApproved',
            'webhook_schedule_status' => 'Approved',
            'status' => 4,
        ]);

        $matchingUser = MatchingUser::query()
            ->where('owner_user_id', 7671402)
            ->where('peer_user_id', 7892390)
            ->where('appointment_schedule_id', 32395)
            ->firstOrFail();

        $this->assertSame('csv_backfill', data_get($matchingUser->webhook_data, '_meta.source'));
    }

    public function test_it_creates_new_rows_when_no_matching_users_row_exists_for_the_pair(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/matching_users_backfill.csv', $this->csvContent([
            [
                'id' => '30529',
                'event_id' => '15781',
                'schedule_start_datetime' => '2026-3-12, 12:30',
                'applicant_visitor_id' => '7544492',
                'applicant_exhibitor_administrator_id' => '',
                'recipient_visitor_id' => '7781179',
                'recipient_exhibitor_administrator_id' => '',
                'applicant_name' => 'Umeno Yasuji',
                'recipient_name' => 'Murayama Kohei',
                'applicant_email' => 'y.umeno@bravesoft.co.jp',
                'recipient_email' => 't.murayama@bravesoft.co.jp',
                'applicant_company_name' => 'bravesoft株式会社',
                'recipient_company_name' => 'bravesoft',
                'applicant_chat_uuid' => '85744eb4-d4ba-41b5-b89e-53efefe5f59d',
                'recipient_chat_uuid' => '024741a9-389d-4385-b4b6-5ac781c2f579',
                'application_source' => 'Chat',
                'exhibitor_administrator_appointment_schedule_id' => '',
                'user_id' => '7544492',
                'booth_id' => '',
                'language_id' => '2',
                'status' => 'Cancel',
                'exhibitor_recipient_memo' => '',
                'exhibitor_applicant_memo' => '',
                'approval_message' => '',
                'cancel_message' => 'キャンセルします',
                'user_name' => 'Umeno Yasuji',
                'user_company' => 'bravesoft株式会社',
                'user_mail_address' => 'y.umeno@bravesoft.co.jp',
                'user_message' => '',
                'user_facehub_direct_meeting_room_id' => '',
                'exhibitor_administrator_facehub_url' => '',
                'user_facehub_url' => '',
                'is_approval_send_mail' => 'false',
                'is_cancel_send_mail' => 'true',
                'is_add_ics' => 'false',
                'approval_message_language_id' => '2',
                'cancel_message_language_id' => '1',
                'business_appointment_room_id' => '24',
                'meeting_location' => '',
                'is_change_meeting_location' => '0',
                'approval_message_cc' => '',
                'cancel_message_cc' => '',
                'approval_message_bcc' => '',
                'cancel_message_bcc' => '',
                'created_at' => '2026-3-11, 22:14',
                'updated_at' => '2026-4-15, 18:27',
            ],
        ]));

        $this->artisan('exhibitor_administrator_appointment_schedule_details', [
            'file' => 'imports/matching_users_backfill.csv',
            '--disk' => 'local',
            '--chunk' => 1,
        ])->assertExitCode(0);

        $this->assertSame(2, MatchingUser::query()->count());

        $this->assertDatabaseHas('matching_users', [
            'event_id' => 15781,
            'owner_user_id' => 7544492,
            'owner_uuid' => '85744eb4-d4ba-41b5-b89e-53efefe5f59d',
            'peer_user_id' => 7781179,
            'peer_uuid' => '024741a9-389d-4385-b4b6-5ac781c2f579',
            'appointment_schedule_id' => 30529,
            'business_appointment_room_id' => 24,
            'schedule_start_datetime' => '2026-03-12 12:30:00',
            'appointment_status' => 'cancelled',
            'webhook_module_code' => 'BusinessAppointmentCancel',
            'webhook_schedule_status' => 'Cancel',
            'status' => 1,
        ]);

        $this->assertDatabaseHas('matching_users', [
            'event_id' => 15781,
            'owner_user_id' => 7781179,
            'owner_uuid' => '024741a9-389d-4385-b4b6-5ac781c2f579',
            'peer_user_id' => 7544492,
            'peer_uuid' => '85744eb4-d4ba-41b5-b89e-53efefe5f59d',
            'appointment_schedule_id' => 30529,
            'business_appointment_room_id' => 24,
            'schedule_start_datetime' => '2026-03-12 12:30:00',
            'appointment_status' => 'cancelled',
            'webhook_module_code' => 'BusinessAppointmentCancel',
            'webhook_schedule_status' => 'Cancel',
            'status' => 1,
        ]);

        $matchingUser = MatchingUser::query()
            ->where('owner_user_id', 7544492)
            ->where('peer_user_id', 7781179)
            ->where('appointment_schedule_id', 30529)
            ->firstOrFail();

        $this->assertSame('csv_backfill', data_get($matchingUser->webhook_data, '_meta.source'));
    }

    public function test_it_skips_rows_with_missing_required_columns_or_unsupported_status(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/matching_users_backfill.csv', $this->csvContent([
            [
                'id' => '40001',
                'event_id' => '14943',
                'schedule_start_datetime' => '2026-4-27, 10:00',
                'applicant_visitor_id' => '7802144',
                'applicant_exhibitor_administrator_id' => '',
                'recipient_visitor_id' => '7778462',
                'recipient_exhibitor_administrator_id' => '',
                'applicant_name' => 'Applicant A',
                'recipient_name' => 'Recipient A',
                'applicant_email' => 'applicant@example.com',
                'recipient_email' => 'recipient@example.com',
                'applicant_company_name' => 'Applicant Co',
                'recipient_company_name' => 'Recipient Co',
                'applicant_chat_uuid' => '',
                'recipient_chat_uuid' => '8578d9a7-a94a-419f-a884-a2a5d88e082a',
                'application_source' => 'Chat',
                'exhibitor_administrator_appointment_schedule_id' => '',
                'user_id' => '7802144',
                'booth_id' => '',
                'language_id' => '2',
                'status' => 'Approved',
                'exhibitor_recipient_memo' => '',
                'exhibitor_applicant_memo' => '',
                'approval_message' => '',
                'cancel_message' => '',
                'user_name' => 'Applicant A',
                'user_company' => 'Applicant Co',
                'user_mail_address' => 'applicant@example.com',
                'user_message' => '',
                'user_facehub_direct_meeting_room_id' => '',
                'exhibitor_administrator_facehub_url' => '',
                'user_facehub_url' => '',
                'is_approval_send_mail' => 'false',
                'is_cancel_send_mail' => 'false',
                'is_add_ics' => 'false',
                'approval_message_language_id' => '2',
                'cancel_message_language_id' => '1',
                'business_appointment_room_id' => '48',
                'meeting_location' => '',
                'is_change_meeting_location' => '0',
                'approval_message_cc' => '',
                'cancel_message_cc' => '',
                'approval_message_bcc' => '',
                'cancel_message_bcc' => '',
                'created_at' => '2026-3-11, 22:14',
                'updated_at' => '2026-4-15, 18:27',
            ],
            [
                'id' => '40002',
                'event_id' => '14943',
                'schedule_start_datetime' => '2026-4-27, 11:00',
                'applicant_visitor_id' => '7802145',
                'applicant_exhibitor_administrator_id' => '',
                'recipient_visitor_id' => '7778463',
                'recipient_exhibitor_administrator_id' => '',
                'applicant_name' => 'Applicant B',
                'recipient_name' => 'Recipient B',
                'applicant_email' => 'applicant-b@example.com',
                'recipient_email' => 'recipient-b@example.com',
                'applicant_company_name' => 'Applicant B Co',
                'recipient_company_name' => 'Recipient B Co',
                'applicant_chat_uuid' => '11111111-1111-4111-8111-111111111111',
                'recipient_chat_uuid' => '22222222-2222-4222-8222-222222222222',
                'application_source' => 'Chat',
                'exhibitor_administrator_appointment_schedule_id' => '',
                'user_id' => '7802145',
                'booth_id' => '',
                'language_id' => '2',
                'status' => 'UnknownStatus',
                'exhibitor_recipient_memo' => '',
                'exhibitor_applicant_memo' => '',
                'approval_message' => '',
                'cancel_message' => '',
                'user_name' => 'Applicant B',
                'user_company' => 'Applicant B Co',
                'user_mail_address' => 'applicant-b@example.com',
                'user_message' => '',
                'user_facehub_direct_meeting_room_id' => '',
                'exhibitor_administrator_facehub_url' => '',
                'user_facehub_url' => '',
                'is_approval_send_mail' => 'false',
                'is_cancel_send_mail' => 'false',
                'is_add_ics' => 'false',
                'approval_message_language_id' => '2',
                'cancel_message_language_id' => '1',
                'business_appointment_room_id' => '49',
                'meeting_location' => '',
                'is_change_meeting_location' => '0',
                'approval_message_cc' => '',
                'cancel_message_cc' => '',
                'approval_message_bcc' => '',
                'cancel_message_bcc' => '',
                'created_at' => '2026-3-11, 22:14',
                'updated_at' => '2026-4-15, 18:27',
            ],
        ]));

        $this->artisan('exhibitor_administrator_appointment_schedule_details', [
            'file' => 'imports/matching_users_backfill.csv',
            '--disk' => 'local',
        ])->assertExitCode(0);

        $this->assertSame(0, MatchingUser::query()->count());
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function csvContent(array $rows): string
    {
        $headers = [
            'id',
            'event_id',
            'schedule_start_datetime',
            'applicant_visitor_id',
            'applicant_exhibitor_administrator_id',
            'recipient_visitor_id',
            'recipient_exhibitor_administrator_id',
            'applicant_name',
            'recipient_name',
            'applicant_email',
            'recipient_email',
            'applicant_company_name',
            'recipient_company_name',
            'applicant_chat_uuid',
            'recipient_chat_uuid',
            'application_source',
            'exhibitor_administrator_appointment_schedule_id',
            'user_id',
            'booth_id',
            'language_id',
            'status',
            'exhibitor_recipient_memo',
            'exhibitor_applicant_memo',
            'approval_message',
            'cancel_message',
            'user_name',
            'user_company',
            'user_mail_address',
            'user_message',
            'user_facehub_direct_meeting_room_id',
            'exhibitor_administrator_facehub_url',
            'user_facehub_url',
            'is_approval_send_mail',
            'is_cancel_send_mail',
            'is_add_ics',
            'approval_message_language_id',
            'cancel_message_language_id',
            'business_appointment_room_id',
            'meeting_location',
            'is_change_meeting_location',
            'approval_message_cc',
            'cancel_message_cc',
            'approval_message_bcc',
            'cancel_message_bcc',
            'created_at',
            'updated_at',
        ];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            $values = [];

            foreach ($headers as $header) {
                $values[] = $row[$header];
            }

            fputcsv($stream, $values);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
