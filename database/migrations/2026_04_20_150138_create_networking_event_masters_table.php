<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('networking_event_masters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_name_ja');
            $table->string('event_name_en');
            $table->unsignedInteger('expected_participants');
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->time('matching_display_time');
            $table->uuid('reception_terminal_id_stg');
            $table->uuid('reception_terminal_id_prod');
            $table->string('checkin_app_user_name')->nullable();
            $table->timestamps();

            $table->index('reception_terminal_id_stg', 'idx_networking_event_masters_terminal_stg');
            $table->index('reception_terminal_id_prod', 'idx_networking_event_masters_terminal_prod');
            $table->index(['event_date', 'start_time'], 'idx_networking_event_masters_schedule');
            $table->index('checkin_app_user_name', 'idx_networking_event_masters_checkin_name');
        });

        $timestamp = now();
        $rows = [
            [
                'event_name_ja' => 'ネットワーキングイベントA',
                'event_name_en' => 'ネットワーキングイベントA',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '11:00:00',
                'end_time' => '11:45:00',
                'matching_display_time' => '11:15:00',
                'reception_terminal_id_stg' => 'bdab95c1-de40-450b-b696-2a130800f541',
                'reception_terminal_id_prod' => 'eebda79a-0c1f-48fc-824a-d84ef75e18e8',
                'checkin_app_user_name' => 'ネットワーキングイベントA',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントB',
                'event_name_en' => 'ネットワーキングイベントB',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '12:00:00',
                'end_time' => '12:45:00',
                'matching_display_time' => '12:15:00',
                'reception_terminal_id_stg' => 'c6c19209-da33-411c-b385-9d2cc40e853b',
                'reception_terminal_id_prod' => '5e4e2d73-1f8d-43c2-8e28-adacefa8b465',
                'checkin_app_user_name' => 'ネットワーキングイベントB',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントC',
                'event_name_en' => 'ネットワーキングイベントC',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '13:00:00',
                'end_time' => '13:45:00',
                'matching_display_time' => '13:15:00',
                'reception_terminal_id_stg' => 'b5b5a763-a401-486f-a876-fbc2dc827e2e',
                'reception_terminal_id_prod' => 'baaa496c-8414-46b6-b380-73d9ab897154',
                'checkin_app_user_name' => 'ネットワーキングイベントC',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントD',
                'event_name_en' => 'ネットワーキングイベントD',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '14:00:00',
                'end_time' => '14:45:00',
                'matching_display_time' => '14:15:00',
                'reception_terminal_id_stg' => '71c104dc-d777-472e-9dac-804aef1e95b0',
                'reception_terminal_id_prod' => '64fc86f2-1a87-42cd-960d-08869b854080',
                'checkin_app_user_name' => 'ネットワーキングイベントD',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントE',
                'event_name_en' => 'ネットワーキングイベントE',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '15:00:00',
                'end_time' => '15:45:00',
                'matching_display_time' => '15:15:00',
                'reception_terminal_id_stg' => 'e995c782-e560-4dfe-8852-26dd076b303e',
                'reception_terminal_id_prod' => '61cfcf5b-b961-40f2-b5bd-69314168effb',
                'checkin_app_user_name' => 'ネットワーキングイベントE',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントF',
                'event_name_en' => 'ネットワーキングイベントF',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '16:00:00',
                'end_time' => '16:45:00',
                'matching_display_time' => '16:15:00',
                'reception_terminal_id_stg' => '9fd85687-9471-4cf8-84bc-bb1bd810f9e3',
                'reception_terminal_id_prod' => '601dfebb-155f-4cb6-8047-5559c121100f',
                'checkin_app_user_name' => 'ネットワーキングイベントF',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントG',
                'event_name_en' => 'ネットワーキングイベントG',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '17:00:00',
                'end_time' => '17:45:00',
                'matching_display_time' => '17:15:00',
                'reception_terminal_id_stg' => 'f9232f72-008e-42ba-925c-8936aa4ba052',
                'reception_terminal_id_prod' => '16693240-6211-4c55-bed8-736be409501b',
                'checkin_app_user_name' => 'ネットワーキングイベントG',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントH',
                'event_name_en' => 'ネットワーキングイベントH',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '11:00:00',
                'end_time' => '11:45:00',
                'matching_display_time' => '11:15:00',
                'reception_terminal_id_stg' => '5a635a36-4224-4517-90c4-cd9f0ff7a734',
                'reception_terminal_id_prod' => '47eb982a-ec21-4389-b90c-2c2fb023f7e0',
                'checkin_app_user_name' => 'ネットワーキングイベントH',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントI',
                'event_name_en' => 'ネットワーキングイベントI',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '12:00:00',
                'end_time' => '12:45:00',
                'matching_display_time' => '12:15:00',
                'reception_terminal_id_stg' => '9845e158-93ac-47d4-898f-e3e0d70734bd',
                'reception_terminal_id_prod' => '1e3873e3-d625-46ad-8993-c59c0e68f372',
                'checkin_app_user_name' => 'ネットワーキングイベントI',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントJ',
                'event_name_en' => 'ネットワーキングイベントJ',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '13:00:00',
                'end_time' => '13:45:00',
                'matching_display_time' => '13:15:00',
                'reception_terminal_id_stg' => '1a648bbe-2afc-4551-b4db-379aaaf6bf9e',
                'reception_terminal_id_prod' => '60dee297-f957-4339-ba8d-ec88805eba50',
                'checkin_app_user_name' => 'ネットワーキングイベントJ',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベンK',
                'event_name_en' => 'ネットワーキングイベンK',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '14:00:00',
                'end_time' => '14:45:00',
                'matching_display_time' => '14:15:00',
                'reception_terminal_id_stg' => '0c9ab125-5b19-4944-8afb-dac6a4a069c7',
                'reception_terminal_id_prod' => '4e674135-e658-4d3a-8e47-1cd90e4e7804',
                'checkin_app_user_name' => 'ネットワーキングイベンK',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントL',
                'event_name_en' => 'ネットワーキングイベントL',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '15:00:00',
                'end_time' => '15:45:00',
                'matching_display_time' => '15:15:00',
                'reception_terminal_id_stg' => '275c9df7-0efa-4c7b-9be2-90696ce46cc0',
                'reception_terminal_id_prod' => '0372a143-b890-4d14-8146-61ddd26a0cc0',
                'checkin_app_user_name' => 'ネットワーキングイベントL',
            ],
            [
                'event_name_ja' => 'ネットワーキングイベントM',
                'event_name_en' => 'ネットワーキングイベントM',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '16:00:00',
                'end_time' => '16:45:00',
                'matching_display_time' => '16:15:00',
                'reception_terminal_id_stg' => '87beeadd-2679-4fe9-8bab-7511a08c8893',
                'reception_terminal_id_prod' => 'd2f55453-28d9-47dd-bac9-ca96b0a78d65',
                'checkin_app_user_name' => 'ネットワーキングイベントM',
            ],
        ];

        DB::table('networking_event_masters')->insert(array_map(
            static fn (array $row): array => [
                ...$row,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $rows,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('networking_event_masters');
    }
};
