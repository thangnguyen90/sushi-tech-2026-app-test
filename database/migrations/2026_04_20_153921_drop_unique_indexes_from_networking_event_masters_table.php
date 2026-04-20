<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('networking_event_masters', function (Blueprint $table) {
                $table->dropUnique('uq_networking_event_masters_terminal_stg');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('networking_event_masters', function (Blueprint $table) {
                $table->dropUnique('uq_networking_event_masters_terminal_prod');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('networking_event_masters', function (Blueprint $table) {
                $table->index('reception_terminal_id_stg', 'idx_networking_event_masters_terminal_stg');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('networking_event_masters', function (Blueprint $table) {
                $table->index('reception_terminal_id_prod', 'idx_networking_event_masters_terminal_prod');
            });
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        try {
            Schema::table('networking_event_masters', function (Blueprint $table) {
                $table->dropIndex('idx_networking_event_masters_terminal_stg');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('networking_event_masters', function (Blueprint $table) {
                $table->dropIndex('idx_networking_event_masters_terminal_prod');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('networking_event_masters', function (Blueprint $table) {
                $table->unique('reception_terminal_id_stg', 'uq_networking_event_masters_terminal_stg');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('networking_event_masters', function (Blueprint $table) {
                $table->unique('reception_terminal_id_prod', 'uq_networking_event_masters_terminal_prod');
            });
        } catch (\Throwable) {
        }
    }
};
