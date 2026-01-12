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
        Schema::create('matching_requests', function (Blueprint $table) {
            $table->bigIncrements('id');                 // BIGINT UNSIGNED
            $table->string('requester_uuid');            // varchar
            $table->string('target_uuid');               // varchar
            $table->tinyInteger('status');               // TINYINT

            $table->index(['requester_uuid']);
            $table->index(['target_uuid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matching_requests');
    }
};
