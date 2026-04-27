<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('matching_users', 'idx_mu_owner_peer_deleted_status_appt')) {
            return;
        }

        Schema::table('matching_users', function (Blueprint $table): void {
            $table->index(
                ['owner_user_id', 'peer_user_id', 'deleted_at', 'status', 'appointment_status'],
                'idx_mu_owner_peer_deleted_status_appt'
            );
        });
    }

    public function down(): void
    {
        if (! $this->indexExists('matching_users', 'idx_mu_owner_peer_deleted_status_appt')) {
            return;
        }

        Schema::table('matching_users', function (Blueprint $table): void {
            $table->dropIndex('idx_mu_owner_peer_deleted_status_appt');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $result = DB::selectOne(
                'SELECT COUNT(*) AS aggregate
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = ?',
                [$table, $indexName]
            );
        } catch (\Throwable) {
            return false;
        }

        return ((int) ($result->aggregate ?? 0)) > 0;
    }
};
