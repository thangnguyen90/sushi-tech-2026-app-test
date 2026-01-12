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
        Schema::create('user_live_chat_tags', function (Blueprint $table) {
            $table->bigIncrements('id'); // BIGINT UNSIGNED, AI, PK

            $table->unsignedBigInteger('live_chat_data_source_id');   // NN
            $table->unsignedBigInteger('user_live_chat_profile_id');  // NN
            $table->unsignedBigInteger('live_chat_tag_id');           // NN

            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_live_chat_tags');
    }
};
