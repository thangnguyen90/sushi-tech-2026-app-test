<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('live_chat_profiles', 'participation_attributes')) {
            return;
        }

        Schema::table('live_chat_profiles', function (Blueprint $table) {
            $table->string('participation_attributes')
                ->nullable()
                ->comment('参加属性')
                ->after('user_company');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('live_chat_profiles', 'participation_attributes')) {
            return;
        }

        Schema::table('live_chat_profiles', function (Blueprint $table) {
            $table->dropColumn('participation_attributes');
        });
    }
};
