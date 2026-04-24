<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_csv_download_settings', function (Blueprint $table) {
            $table->id();
            $table->string('encryption_key');
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        $now = now();

        DB::table('matching_csv_download_settings')->insert([
            'encryption_key' => Str::lower(Str::random(16)),
            'is_enabled' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_csv_download_settings');
    }
};
