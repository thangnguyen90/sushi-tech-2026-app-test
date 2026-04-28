<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_csv_download_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('rate_limit_per_day')->default(10)->after('is_enabled');
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(3)->after('rate_limit_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('matching_csv_download_settings', function (Blueprint $table) {
            $table->dropColumn(['rate_limit_per_day', 'rate_limit_per_minute']);
        });
    }
};
