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
        Schema::create('live_chat_profile_field_options', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('profile_id');
            $table->string('field_key', 128);
            $table->unsignedBigInteger('option_id');
            $table->string('option_value', 128);

            $table->timestamps();
            $table->softDeletes();

            // Single-select per field_key (dropdown)
            $table->unique(['profile_id', 'field_key'], 'uq_profile_field');

            // Search index: (field_key, option_id) -> profile_id
            $table->index(['field_key', 'option_id', 'profile_id'], 'idx_field_option_profile');

            // Load profile selections quickly
            $table->index(['profile_id'], 'idx_profile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_chat_profile_field_options');
    }
};
