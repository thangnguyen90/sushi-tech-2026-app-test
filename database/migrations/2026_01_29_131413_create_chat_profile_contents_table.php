<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_profile_contents', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('field_key', 128);
            $table->string('option_value', 128);

            $table->string('label_eng', 255);
            $table->string('label_jpn', 255);

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['field_key', 'option_value'], 'uq_chat_profile_contents_field_option');
            $table->index('field_key', 'idx_chat_profile_contents_field');
            $table->index('option_value', 'idx_chat_profile_contents_option');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_profile_contents');
    }
};
