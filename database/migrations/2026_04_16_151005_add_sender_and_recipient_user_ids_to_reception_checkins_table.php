<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_checkins', function (Blueprint $table) {
            $table->unsignedBigInteger('applicant_user_id')->nullable()->after('business_appointment_room_id');
            $table->uuid('applicant_user_uuid')->nullable()->after('applicant_user_id');
            $table->unsignedBigInteger('recipient_user_id')->nullable()->after('applicant_user_uuid');
            $table->uuid('recipient_user_uuid')->nullable()->after('recipient_user_id');

            $table->index('applicant_user_id', 'idx_reception_checkins_applicant_user_id');
            $table->index('recipient_user_id', 'idx_reception_checkins_recipient_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('reception_checkins', function (Blueprint $table) {
            $table->dropIndex('idx_reception_checkins_applicant_user_id');
            $table->dropIndex('idx_reception_checkins_recipient_user_id');
            $table->dropColumn([
                'applicant_user_id',
                'applicant_user_uuid',
                'recipient_user_id',
                'recipient_user_uuid',
            ]);
        });
    }
};
