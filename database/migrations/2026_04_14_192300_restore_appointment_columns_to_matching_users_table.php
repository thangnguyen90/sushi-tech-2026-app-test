<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('matching_users', 'appointment_schedule_id')) {
                $table->unsignedBigInteger('appointment_schedule_id')->nullable()->after('event_id');
            }
            if (! Schema::hasColumn('matching_users', 'business_appointment_room_id')) {
                $table->unsignedBigInteger('business_appointment_room_id')->nullable()->after('appointment_schedule_id');
            }
            if (! Schema::hasColumn('matching_users', 'schedule_start_datetime')) {
                $table->dateTime('schedule_start_datetime')->nullable()->after('business_appointment_room_id');
            }
            if (! Schema::hasColumn('matching_users', 'appointment_status')) {
                $table->string('appointment_status')->nullable()->after('schedule_start_datetime');
            }
            if (! Schema::hasColumn('matching_users', 'webhook_module_code')) {
                $table->string('webhook_module_code')->nullable()->after('appointment_status');
            }
            if (! Schema::hasColumn('matching_users', 'webhook_schedule_status')) {
                $table->string('webhook_schedule_status')->nullable()->after('webhook_module_code');
            }
            if (! Schema::hasColumn('matching_users', 'webhook_data')) {
                $table->json('webhook_data')->nullable()->after('webhook_schedule_status');
            }
        });

        if (! $this->indexExists('matching_users', 'idx_matching_users_appointment_schedule_owner')) {
            Schema::table('matching_users', function (Blueprint $table): void {
                $table->index(
                    ['appointment_schedule_id', 'owner_user_id'],
                    'idx_matching_users_appointment_schedule_owner'
                );
            });
        }

        if (! $this->indexExists('matching_users', 'idx_matching_users_owner_appointment_status')) {
            Schema::table('matching_users', function (Blueprint $table): void {
                $table->index(
                    ['owner_user_id', 'appointment_status'],
                    'idx_matching_users_owner_appointment_status'
                );
            });
        }

        DB::table('matching_users')
            ->where('status', 4)
            ->whereNull('appointment_status')
            ->update([
                'appointment_status' => 'approved',
                'webhook_schedule_status' => 'Approved',
            ]);
    }

    public function down(): void
    {
        if ($this->indexExists('matching_users', 'idx_matching_users_appointment_schedule_owner')) {
            Schema::table('matching_users', function (Blueprint $table): void {
                $table->dropIndex('idx_matching_users_appointment_schedule_owner');
            });
        }

        if ($this->indexExists('matching_users', 'idx_matching_users_owner_appointment_status')) {
            Schema::table('matching_users', function (Blueprint $table): void {
                $table->dropIndex('idx_matching_users_owner_appointment_status');
            });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('matching_users', 'appointment_schedule_id') ? 'appointment_schedule_id' : null,
            Schema::hasColumn('matching_users', 'business_appointment_room_id') ? 'business_appointment_room_id' : null,
            Schema::hasColumn('matching_users', 'schedule_start_datetime') ? 'schedule_start_datetime' : null,
            Schema::hasColumn('matching_users', 'appointment_status') ? 'appointment_status' : null,
            Schema::hasColumn('matching_users', 'webhook_module_code') ? 'webhook_module_code' : null,
            Schema::hasColumn('matching_users', 'webhook_schedule_status') ? 'webhook_schedule_status' : null,
            Schema::hasColumn('matching_users', 'webhook_data') ? 'webhook_data' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('matching_users', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
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
