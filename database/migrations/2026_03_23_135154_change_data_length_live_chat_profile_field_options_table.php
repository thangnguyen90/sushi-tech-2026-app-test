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
        Schema::table('live_chat_profile_field_options', function (Blueprint $table) {
            $table->string('option_value', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_chat_profile_field_options', function (Blueprint $table) {
            $table->string('option_value', 128)->nullable(false)->change();
        });
    }
};
