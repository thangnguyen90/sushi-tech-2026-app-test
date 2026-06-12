<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class CsvListTriggerWebhookTest extends TestCase
{
    public function test_it_maps_business_appointment_room_contents_to_business_appointment_rooms_command(): void
    {
        Process::fake();
        Process::preventStrayProcesses();

        $response = $this->postJson('/api/v1/webhook/csv-list-trigger', [
            [
                'name' => 'business_appointment_room_contents',
                'fileurl' => 'csv/2026-04-16-17-53-17_business_appointment_room_contents.csv.gz',
            ],
        ]);

        $response->assertCreated();

        Process::assertRan(static function ($process): bool {
            return $process->command === 'php artisan business_appointment_rooms /csv/2026-04-16-17-53-17_business_appointment_room_contents.csv.gz';
        });

        Process::assertDidntRun(static function ($process): bool {
            return $process->command === 'php artisan business_appointment_room_contents /csv/2026-04-16-17-53-17_business_appointment_room_contents.csv.gz';
        });
    }
}
