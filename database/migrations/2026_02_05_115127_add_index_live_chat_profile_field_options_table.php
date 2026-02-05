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
        Schema::table('live_chat_profile_field_options', function (Blueprint $table) {
            // Add composite index for (profile_id, option_value, deleted_at)
            $table->index(
                ['profile_id', 'option_value', 'deleted_at'],
                'idx_profile_option_deleted'
            );

            // Drop redundant single-column index
            $table->dropIndex('idx_profile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_chat_profile_field_options', function (Blueprint $table) {
            // Restore dropped index
            $table->index(['profile_id'], 'idx_profile');

            // Drop added composite index
            $table->dropIndex('idx_profile_option_deleted');
        });
    }
};
