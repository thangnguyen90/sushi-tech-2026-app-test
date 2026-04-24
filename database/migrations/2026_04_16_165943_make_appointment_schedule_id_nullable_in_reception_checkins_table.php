<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_checkins', function (Blueprint $table): void {
            $table->unsignedBigInteger('appointment_schedule_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reception_checkins', function (Blueprint $table): void {
            $table->unsignedBigInteger('appointment_schedule_id')->nullable(false)->change();
        });
    }
};
