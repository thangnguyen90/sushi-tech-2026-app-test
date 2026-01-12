<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_chat_profiles', function (Blueprint $table) {
            $table->bigIncrements('id'); // BIGINT UNSIGNED

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('uuid')->nullable();
            $table->boolean('policy_agreed')->default(false);

            $table->string('live_chat_user_id', 255)->nullable();

            $table->unsignedBigInteger('live_chat_data_source_id')->nullable();

            $table->unsignedBigInteger('exhibitor_administrator_id')->nullable();

            $table->string('nickname')->nullable();
            $table->string('icon_image')->nullable();
            $table->string('background_image')->nullable();
            $table->string('mail_address')->nullable();

            $table->text('introduction')->nullable();
            $table->string('company')->nullable();

            $table->unsignedBigInteger('last_portal_id')->nullable();
            $table->unsignedBigInteger('last_event_id')->nullable();

            $table->json('custom_fields')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('deleted_at')->nullable();
            $table->index('uuid');
            $table->index(['uuid', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_chat_profiles');
    }
};
