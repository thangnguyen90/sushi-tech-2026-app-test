<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('reception_checkins', 'reception_checkins_legacy');

        Schema::create('reception_checkins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('appointment_schedule_id');
            $table->unsignedBigInteger('business_appointment_room_id')->nullable();
            $table->uuid('user_uuid');
            $table->string('checkin_status', 32);
            $table->dateTime('checkin_at');
            $table->timestamps();

            $table->index('appointment_schedule_id', 'idx_reception_checkins_appointment_v2');
            $table->index('business_appointment_room_id', 'idx_reception_checkins_room_v2');
            $table->index('user_uuid', 'idx_reception_checkins_user_uuid_v2');
            $table->index('checkin_status', 'idx_reception_checkins_status_v2');
        });

        DB::table('reception_checkins_legacy')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                $payloads = [];

                foreach ($rows as $row) {
                    if ($row->first_checkin_user_uuid !== null && $row->first_checkin_at !== null) {
                        $payloads[] = [
                            'appointment_schedule_id' => $row->appointment_schedule_id,
                            'business_appointment_room_id' => $row->business_appointment_room_id,
                            'user_uuid' => $row->first_checkin_user_uuid,
                            'checkin_status' => 'first_checkin',
                            'checkin_at' => $row->first_checkin_at,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ];
                    }

                    if ($row->second_checkin_user_uuid !== null && $row->second_checkin_at !== null) {
                        $payloads[] = [
                            'appointment_schedule_id' => $row->appointment_schedule_id,
                            'business_appointment_room_id' => $row->business_appointment_room_id,
                            'user_uuid' => $row->second_checkin_user_uuid,
                            'checkin_status' => 'second_checkin',
                            'checkin_at' => $row->second_checkin_at,
                            'created_at' => $row->created_at,
                            'updated_at' => $row->updated_at,
                        ];
                    }
                }

                if ($payloads !== []) {
                    DB::table('reception_checkins')->insert($payloads);
                }
            });

        Schema::drop('reception_checkins_legacy');
    }

    public function down(): void
    {
        Schema::rename('reception_checkins', 'reception_checkins_v2');

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

        $groupedRows = DB::table('reception_checkins_v2')
            ->orderBy('appointment_schedule_id')
            ->orderBy('id')
            ->get()
            ->groupBy('appointment_schedule_id');

        $payloads = [];
        foreach ($groupedRows as $appointmentScheduleId => $rows) {
            $first = $rows->firstWhere('checkin_status', 'first_checkin') ?? $rows->first();
            $second = $rows->firstWhere('checkin_status', 'second_checkin');

            $payloads[] = [
                'appointment_schedule_id' => $appointmentScheduleId,
                'business_appointment_room_id' => $first?->business_appointment_room_id,
                'first_checkin_user_uuid' => $first?->user_uuid,
                'first_checkin_at' => $first?->checkin_at,
                'second_checkin_user_uuid' => $second?->user_uuid,
                'second_checkin_at' => $second?->checkin_at,
                'created_at' => $first?->created_at ?? now(),
                'updated_at' => $rows->last()?->updated_at ?? now(),
            ];
        }

        if ($payloads !== []) {
            DB::table('reception_checkins')->insert($payloads);
        }

        Schema::drop('reception_checkins_v2');
    }
};
