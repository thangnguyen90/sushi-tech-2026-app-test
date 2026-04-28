<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('csv_download_logs', function (Blueprint $table) {
            $table->boolean('is_suspicious')->default(false)->after('downloaded_user_count');
            $table->json('suspicious_flags')->nullable()->after('is_suspicious');
        });
    }

    public function down(): void
    {
        Schema::table('csv_download_logs', function (Blueprint $table) {
            $table->dropColumn(['is_suspicious', 'suspicious_flags']);
        });
    }
};
