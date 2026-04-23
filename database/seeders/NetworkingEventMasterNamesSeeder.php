<?php

namespace Database\Seeders;

use App\Models\NetworkingEventMaster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NetworkingEventMasterNamesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'terminal_name' => 'NEXUS',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '10:00:00',
                'end_time' => '10:45:00',
                'matching_display_time' => '10:15:00',
            ],
            [
                'terminal_name' => 'Isomer Capital',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '11:00:00',
                'end_time' => '11:45:00',
                'matching_display_time' => '11:15:00',
            ],
            [
                'terminal_name' => 'KDDI株式会社 × Carbide Ventures',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '12:00:00',
                'end_time' => '12:45:00',
                'matching_display_time' => '12:15:00',
            ],
            [
                'terminal_name' => 'Emerald Technology Ventures',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '13:00:00',
                'end_time' => '13:45:00',
                'matching_display_time' => '13:15:00',
            ],
            [
                'terminal_name' => 'Pegasus Tech Ventures, Inc.',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '14:00:00',
                'end_time' => '14:45:00',
                'matching_display_time' => '14:15:00',
            ],
            [
                'terminal_name' => 'CVC',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '15:00:00',
                'end_time' => '15:45:00',
                'matching_display_time' => '15:15:00',
            ],
            [
                'terminal_name' => 'Samsung Electronics Co Ltd.',
                'expected_participants' => 15,
                'event_date' => '2026-04-27',
                'start_time' => '16:00:00',
                'end_time' => '16:45:00',
                'matching_display_time' => '16:15:00',
            ],
            [
                'terminal_name' => 'Zacua Ventures',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '11:00:00',
                'end_time' => '11:45:00',
                'matching_display_time' => '11:15:00',
            ],
            [
                'terminal_name' => 'JCCI Singapore',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '12:00:00',
                'end_time' => '12:45:00',
                'matching_display_time' => '12:15:00',
            ],
            [
                'terminal_name' => 'Sozo Ventures',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '13:00:00',
                'end_time' => '13:45:00',
                'matching_display_time' => '13:15:00',
            ],
            [
                'terminal_name' => 'Double Feather×NEC×FCDO',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '14:00:00',
                'end_time' => '14:45:00',
                'matching_display_time' => '14:15:00',
            ],
            [
                'terminal_name' => 'NVIDIA',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '15:00:00',
                'end_time' => '15:45:00',
                'matching_display_time' => '15:15:00',
            ],
            [
                'terminal_name' => 'Asian Development Bank',
                'expected_participants' => 15,
                'event_date' => '2026-04-28',
                'start_time' => '16:00:00',
                'end_time' => '16:45:00',
                'matching_display_time' => '16:15:00',
            ],
        ];

        DB::transaction(function () use ($rows): void {
            $masters = NetworkingEventMaster::query()
                ->orderBy('id')
                ->get();

            if ($masters->count() !== count($rows)) {
                throw new RuntimeException(sprintf(
                    'Expected %d networking_event_masters rows, found %d.',
                    count($rows),
                    $masters->count(),
                ));
            }

            $timestamp = now();

            foreach ($masters->values() as $index => $master) {
                $row = $rows[$index];

                $master->forceFill([
                    'event_name_ja' => $row['terminal_name'],
                    'event_name_en' => $row['terminal_name'],
                    'checkin_app_user_name' => $row['terminal_name'],
                    'expected_participants' => $row['expected_participants'],
                    'event_date' => $row['event_date'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'matching_display_time' => $row['matching_display_time'],
                    'updated_at' => $timestamp,
                ])->save();
            }
        });
    }
}
