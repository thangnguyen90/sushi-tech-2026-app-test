<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkin_histories', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('exhibitor_administrator_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('event_id');

            $table->string('checkin_app_user_name', 255)->nullable();

            $table->boolean('is_confirm')->default(false);
            // DATETIME
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->dateTime('deleted_at')->nullable();

            // Indexes
            $table->index(['exhibitor_administrator_id', 'checkin_app_user_name'], 'idx_admin_event');
            $table->index(['user_id', 'checkin_app_user_name']);
            $table->index('checkin_app_user_name');

        });

    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_histories');
    }
};
