<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_csv_download_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('off_hours_start')->default(23)->after('burst_window_minutes');
            $table->unsignedTinyInteger('off_hours_end')->default(6)->after('off_hours_start');
        });
    }

    public function down(): void
    {
        Schema::table('matching_csv_download_settings', function (Blueprint $table) {
            $table->dropColumn(['off_hours_start', 'off_hours_end']);
        });
    }
};
