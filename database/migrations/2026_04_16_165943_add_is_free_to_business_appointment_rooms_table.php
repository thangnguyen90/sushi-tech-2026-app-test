<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('business_appointment_rooms', 'is_free')) {
            return;
        }

        Schema::table('business_appointment_rooms', function (Blueprint $table): void {
            $table->boolean('is_free')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('business_appointment_rooms', 'is_free')) {
            return;
        }

        Schema::table('business_appointment_rooms', function (Blueprint $table): void {
            $table->dropColumn('is_free');
        });
    }
};
