<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reception_checkins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('appointment_schedule_id');
            $table->unsignedBigInteger('business_appointment_room_id')->nullable();
            $table->uuid('first_checkin_user_uuid')->nullable();
            $table->dateTime('first_checkin_at')->nullable();
            $table->uuid('second_checkin_user_uuid')->nullable();
            $table->dateTime('second_checkin_at')->nullable();
            $table->timestamps();

            $table->unique('appointment_schedule_id', 'uq_reception_checkins_appointment');
            $table->index('business_appointment_room_id', 'idx_reception_checkins_room');
            $table->index('first_checkin_user_uuid', 'idx_reception_checkins_first_user_uuid');
            $table->index('second_checkin_user_uuid', 'idx_reception_checkins_second_user_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reception_checkins');
    }
};
