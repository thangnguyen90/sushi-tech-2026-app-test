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
        Schema::create('matching_users', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('event_id');

            $table->unsignedBigInteger('owner_user_id');
            $table->unsignedBigInteger('peer_user_id');

            $table->tinyInteger('status')->comment('1.申請中, 2.マッチングリクエスト, 3.マッチング済, 4.商談済');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_user_id', 'status', 'peer_user_id'], 'idx_event_owner_status_peer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matching_users');
    }
};
