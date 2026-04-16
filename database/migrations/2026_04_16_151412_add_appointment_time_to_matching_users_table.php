<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('matching_users', 'appointment_time')) {
                $table->dateTime('appointment_time')->nullable()->after('schedule_start_datetime');
            }
        });

        DB::table('matching_users')
            ->whereNull('appointment_time')
            ->whereNotNull('schedule_start_datetime')
            ->update([
                'appointment_time' => DB::raw('schedule_start_datetime'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('matching_users', 'appointment_time')) {
            return;
        }

        Schema::table('matching_users', function (Blueprint $table): void {
            $table->dropColumn('appointment_time');
        });
    }
};
