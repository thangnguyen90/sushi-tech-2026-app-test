<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('matching_csv_download_settings', 'rate_limit_per_day')) {
            Schema::table('matching_csv_download_settings', function (Blueprint $table) {
                $table->dropColumn('rate_limit_per_day');
            });
        }
    }

    public function down(): void
    {
        Schema::table('matching_csv_download_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('rate_limit_per_day')->default(10)->after('is_enabled');
        });
    }
};
