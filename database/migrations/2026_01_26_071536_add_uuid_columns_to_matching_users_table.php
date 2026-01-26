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
        Schema::table('matching_users', function (Blueprint $table) {
            $table->uuid('owner_uuid')->nullable()->after('owner_user_id');
            $table->uuid('peer_uuid')->nullable()->after('peer_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matching_users', function (Blueprint $table) {
            $table->dropColumn(['owner_uuid', 'peer_uuid']);
        });
    }
};
