<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_chat_profiles', function (Blueprint $table): void {
            if (! $this->indexExists('live_chat_profiles', 'idx_lcp_partner_lookup')) {
                $table->index(
                    ['live_chat_data_source_id', 'last_event_id', 'is_exhibitor', 'deleted_at', 'user_id', 'id'],
                    'idx_lcp_partner_lookup'
                );
            }
        });

        Schema::table('matching_users', function (Blueprint $table): void {
            if (! $this->indexExists('matching_users', 'idx_mu_owner_peer')) {
                $table->index(['owner_user_id', 'peer_user_id'], 'idx_mu_owner_peer');
            }
            if (! $this->indexExists('matching_users', 'idx_mu_peer_owner')) {
                $table->index(['peer_user_id', 'owner_user_id'], 'idx_mu_peer_owner');
            }
        });
    }

    public function down(): void
    {
        Schema::table('live_chat_profiles', function (Blueprint $table): void {
            if ($this->indexExists('live_chat_profiles', 'idx_lcp_partner_lookup')) {
                $table->dropIndex('idx_lcp_partner_lookup');
            }
        });

        Schema::table('matching_users', function (Blueprint $table): void {
            if ($this->indexExists('matching_users', 'idx_mu_owner_peer')) {
                $table->dropIndex('idx_mu_owner_peer');
            }
            if ($this->indexExists('matching_users', 'idx_mu_peer_owner')) {
                $table->dropIndex('idx_mu_peer_owner');
            }
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
