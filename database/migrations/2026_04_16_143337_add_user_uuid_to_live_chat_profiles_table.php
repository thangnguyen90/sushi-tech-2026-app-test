<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_chat_profiles', function (Blueprint $table) {
            $table->string('user_uuid')->nullable()->after('user_id');
            $table->index('user_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('live_chat_profiles', function (Blueprint $table) {
            $table->dropIndex(['user_uuid']);
            $table->dropColumn('user_uuid');
        });
    }
};
