<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_csv_download_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('large_batch_threshold')->default(10)->after('api_timeout_seconds');
            $table->unsignedSmallInteger('high_freq_day_threshold')->default(5)->after('large_batch_threshold');
            $table->unsignedSmallInteger('burst_threshold')->default(3)->after('high_freq_day_threshold');
            $table->unsignedSmallInteger('burst_window_minutes')->default(5)->after('burst_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('matching_csv_download_settings', function (Blueprint $table) {
            $table->dropColumn([
                'large_batch_threshold',
                'high_freq_day_threshold',
                'burst_threshold',
                'burst_window_minutes',
            ]);
        });
    }
};
