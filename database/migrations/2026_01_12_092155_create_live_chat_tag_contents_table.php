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
        Schema::create('live_chat_tag_contents', function (Blueprint $table) {
            $table->bigIncrements('id'); // BIGINT UNSIGNED, PK, AI

            $table->unsignedBigInteger('live_chat_tag_id'); // NN
            $table->string('name', 255);                    // NN
            $table->unsignedBigInteger('language_id');      // NN
            $table->tinyInteger('is_publish')->default(0);  // NN (TINYINT)

            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_chat_tag_contents');
    }
};
