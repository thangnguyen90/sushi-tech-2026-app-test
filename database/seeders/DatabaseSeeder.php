<?php

namespace Database\Seeders;

use App\Models\LiveChatProfiles;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // LiveChatProfiles::factory(10)->create();

        LiveChatProfiles::factory()->create([
            'name' => 'Test LiveChatProfiles',
            'email' => 'test@example.com',
        ]);
    }
}
