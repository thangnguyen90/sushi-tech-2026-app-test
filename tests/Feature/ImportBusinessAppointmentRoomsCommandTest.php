<?php

namespace Tests\Feature;

use App\Models\BusinessAppointmentRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportBusinessAppointmentRoomsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_and_updates_business_appointment_rooms_from_local_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/business_appointment_rooms.csv', $this->csvContent([
            [
                'business_appointment_room_id' => '48',
                'content_id' => '424136',
                'name' => 'Matching Area A',
                'room_image' => '{"file":"/images/a.png","mime":"image/png","size":100,"width":960,"height":540}',
                'language_id' => '2',
                'created_at' => '2026-3-9, 21:34',
                'updated_at' => '2026-4-10, 11:23',
                'deleted_at' => '',
            ],
            [
                'business_appointment_room_id' => '48',
                'content_id' => '424136',
                'name' => '商談エリアA',
                'room_image' => '{"file":"/images/a-jp.png","mime":"image/png","size":120,"width":960,"height":540}',
                'language_id' => '1',
                'created_at' => '2026-3-9, 21:34',
                'updated_at' => '2026-4-10, 11:23',
                'deleted_at' => '',
            ],
            [
                'business_appointment_room_id' => '49',
                'content_id' => '424136',
                'name' => 'Matching Area B',
                'room_image' => '{"file":"/images/b.png","mime":"image/png","size":150,"width":960,"height":540}',
                'language_id' => '2',
                'created_at' => '2026-3-9, 21:35',
                'updated_at' => '2026-4-10, 11:24',
                'deleted_at' => '2026-4-11, 09:05',
            ],
        ]));

        $this->artisan('business_appointment_rooms', [
            'file' => 'imports/business_appointment_rooms.csv',
            '--disk' => 'local',
            '--chunk' => 2,
        ])->assertExitCode(0);

        $this->assertSame(3, BusinessAppointmentRoom::withTrashed()->count());
        $this->assertDatabaseHas('business_appointment_rooms', [
            'business_appointment_room_id' => 48,
            'language_id' => 2,
            'name' => 'Matching Area A',
            'content_id' => 424136,
            'created_at' => '2026-03-09 21:34:00',
            'updated_at' => '2026-04-10 11:23:00',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('business_appointment_rooms', [
            'business_appointment_room_id' => 49,
            'language_id' => 2,
            'deleted_at' => '2026-04-11 09:05:00',
        ]);

        $englishRoom = BusinessAppointmentRoom::withTrashed()
            ->where('business_appointment_room_id', 48)
            ->where('language_id', 2)
            ->firstOrFail();

        $this->assertSame('/images/a.png', $englishRoom->room_image['file']);

        Storage::disk('local')->put('imports/business_appointment_rooms.csv', $this->csvContent([
            [
                'business_appointment_room_id' => '48',
                'content_id' => '424136',
                'name' => 'Matching Area A Updated',
                'room_image' => '{"file":"/images/a-updated.png","mime":"image/png","size":101,"width":960,"height":540}',
                'language_id' => '2',
                'created_at' => '2026-3-9, 21:34',
                'updated_at' => '2026-4-12, 12:30',
                'deleted_at' => '',
            ],
            [
                'business_appointment_room_id' => '48',
                'content_id' => '424136',
                'name' => '商談エリアA',
                'room_image' => '{"file":"/images/a-jp.png","mime":"image/png","size":120,"width":960,"height":540}',
                'language_id' => '1',
                'created_at' => '2026-3-9, 21:34',
                'updated_at' => '2026-4-10, 11:23',
                'deleted_at' => '',
            ],
        ]));

        $this->artisan('business_appointment_rooms', [
            'file' => 'imports/business_appointment_rooms.csv',
            '--disk' => 'local',
            '--chunk' => 1,
        ])->assertExitCode(0);

        $this->assertSame(3, BusinessAppointmentRoom::withTrashed()->count());

        $updatedRoom = BusinessAppointmentRoom::withTrashed()
            ->where('business_appointment_room_id', 48)
            ->where('language_id', 2)
            ->firstOrFail();

        $this->assertSame('Matching Area A Updated', $updatedRoom->name);
        $this->assertSame('/images/a-updated.png', $updatedRoom->room_image['file']);
        $this->assertSame('2026-04-12 12:30:00', $updatedRoom->getRawOriginal('updated_at'));
    }

    public function test_it_imports_gzip_files_from_local_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(
            'imports/business_appointment_rooms.csv.gz',
            gzencode($this->csvContent([
                [
                    'business_appointment_room_id' => '79',
                    'content_id' => '424136',
                    'name' => 'Matching Area G',
                    'room_image' => '{"file":"/images/g.png","mime":"image/png","size":111,"width":960,"height":540}',
                    'language_id' => '2',
                    'created_at' => '2026-3-9, 21:40',
                    'updated_at' => '2026-4-10, 11:28',
                    'deleted_at' => '',
                ],
            ]))
        );

        $this->artisan('business_appointment_rooms', [
            'file' => 'imports/business_appointment_rooms.csv.gz',
            '--disk' => 'local',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('business_appointment_rooms', [
            'business_appointment_room_id' => 79,
            'language_id' => 2,
            'name' => 'Matching Area G',
        ]);
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function csvContent(array $rows): string
    {
        $headers = [
            'business_appointment_room_id',
            'content_id',
            'name',
            'room_image',
            'language_id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['business_appointment_room_id'],
                $row['content_id'],
                $row['name'],
                $row['room_image'],
                $row['language_id'],
                $row['created_at'],
                $row['updated_at'],
                $row['deleted_at'],
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
