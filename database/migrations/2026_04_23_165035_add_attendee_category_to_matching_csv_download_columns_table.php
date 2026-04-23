<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('matching_csv_download_columns')) {
            return;
        }

        DB::transaction(function (): void {
            $existingColumn = DB::table('matching_csv_download_columns')
                ->where('column_key', 'attendee_category')
                ->first();

            if ($existingColumn === null) {
                DB::table('matching_csv_download_columns')
                    ->where('sort_order', '>=', 5)
                    ->increment('sort_order');

                DB::table('matching_csv_download_columns')->insert([
                    'column_key' => 'attendee_category',
                    'type' => 'profile',
                    'profile_key' => 'is_exhibitor',
                    'custom_field_key' => null,
                    'label_jpn' => '来場区分',
                    'label_eng' => 'Attendee Category',
                    'sort_order' => 5,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return;
            }

            DB::table('matching_csv_download_columns')
                ->where('column_key', 'attendee_category')
                ->update([
                    'type' => 'profile',
                    'profile_key' => 'is_exhibitor',
                    'custom_field_key' => null,
                    'label_jpn' => '来場区分',
                    'label_eng' => 'Attendee Category',
                    'sort_order' => 5,
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('matching_csv_download_columns')) {
            return;
        }

        DB::transaction(function (): void {
            $deletedCount = DB::table('matching_csv_download_columns')
                ->where('column_key', 'attendee_category')
                ->delete();

            if ($deletedCount > 0) {
                DB::table('matching_csv_download_columns')
                    ->where('sort_order', '>', 5)
                    ->decrement('sort_order');
            }
        });
    }
};
