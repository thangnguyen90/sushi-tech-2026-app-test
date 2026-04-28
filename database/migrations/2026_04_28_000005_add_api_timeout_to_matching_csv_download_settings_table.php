<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_csv_download_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('api_timeout_seconds')->default(3)->after('rate_limit_per_minute');
        });
    }

    public function down(): void
    {
        Schema::table('matching_csv_download_settings', function (Blueprint $table) {
            $table->dropColumn('api_timeout_seconds');
        });
    }
};
