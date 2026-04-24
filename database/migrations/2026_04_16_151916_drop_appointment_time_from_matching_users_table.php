<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('matching_users', 'appointment_time')) {
            return;
        }

        Schema::table('matching_users', function (Blueprint $table): void {
            $table->dropColumn('appointment_time');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('matching_users', 'appointment_time')) {
            return;
        }

        Schema::table('matching_users', function (Blueprint $table): void {
            $table->dateTime('appointment_time')->nullable()->after('schedule_start_datetime');
        });
    }
};
