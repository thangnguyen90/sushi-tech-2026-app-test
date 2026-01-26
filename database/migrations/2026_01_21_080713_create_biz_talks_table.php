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
        Schema::create('biz_talks', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->unsignedInteger('exhibitor_schedule_id');
            $table->unsignedInteger('user_id');
            $table->string('user_uuid', 36);
            $table->timestamp('started_at')->nullable();
            $table->json('data')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biz_talks');
    }
};
