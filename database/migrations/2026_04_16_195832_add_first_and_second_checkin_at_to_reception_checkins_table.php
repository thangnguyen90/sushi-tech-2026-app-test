<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_checkins', function (Blueprint $table): void {
            $table->dateTime('first_checkin_at')->nullable()->after('checkin_at');
            $table->dateTime('second_checkin_at')->nullable()->after('first_checkin_at');
        });

        DB::table('reception_checkins')->where('checkin_status', 'first_checkin')->update([
            'first_checkin_at' => DB::raw('checkin_at'),
        ]);

        DB::table('reception_checkins')->where('checkin_status', 'second_checkin')->update([
            'second_checkin_at' => DB::raw('checkin_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('reception_checkins', function (Blueprint $table): void {
            $table->dropColumn(['first_checkin_at', 'second_checkin_at']);
        });
    }
};
