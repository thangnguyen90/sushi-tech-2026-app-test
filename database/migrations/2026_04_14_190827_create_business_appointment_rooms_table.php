<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_appointment_rooms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_appointment_room_id');
            $table->unsignedBigInteger('content_id')->nullable();
            $table->string('name');
            $table->json('room_image')->nullable();
            $table->unsignedBigInteger('language_id');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->softDeletes();

            $table->unique(
                ['business_appointment_room_id', 'language_id'],
                'uq_business_appointment_rooms_room_language'
            );
            $table->index('business_appointment_room_id', 'idx_business_appointment_rooms_room');
            $table->index('content_id', 'idx_business_appointment_rooms_content');
            $table->index('language_id', 'idx_business_appointment_rooms_language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_appointment_rooms');
    }
};
