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
        Schema::table('chat_profile_contents', function (Blueprint $table) {
            $table->json('language_setting')->nullable()->after('option_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_profile_contents', function (Blueprint $table) {
            $table->dropColumn('language_setting');
        });
    }
};
