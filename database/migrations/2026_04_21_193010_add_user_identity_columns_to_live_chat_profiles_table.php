<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_chat_profiles', function (Blueprint $table) {
            $table->string('user_name')->nullable()->after('is_exhibitor');
            $table->string('user_email')->nullable()->after('user_name');
            $table->string('user_company')->nullable()->after('user_email');
        });
    }

    public function down(): void
    {
        Schema::table('live_chat_profiles', function (Blueprint $table) {
            $table->dropColumn(['user_name', 'user_email', 'user_company']);
        });
    }
};
