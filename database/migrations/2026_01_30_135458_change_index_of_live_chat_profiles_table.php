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
        Schema::table('live_chat_profiles', function (Blueprint $table) {
            $table->dropIndex(['uuid']);
            $table->dropIndex(['uuid', 'user_id']);
            $table->index(['user_id', 'exhibitor_administrator_id', 'mail_address'], 'live_chat_profiles_user_exhibitor_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
