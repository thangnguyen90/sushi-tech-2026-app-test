<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_csv_download_columns', function (Blueprint $table) {
            $table->id();
            $table->string('column_key')->unique();
            $table->string('type', 32);
            $table->string('profile_key')->nullable();
            $table->string('custom_field_key')->nullable();
            $table->string('label_jpn');
            $table->string('label_eng');
            $table->unsignedInteger('sort_order');
            $table->timestamps();
        });

        $now = now();

        DB::table('matching_csv_download_columns')->insert([
            [
                'column_key' => 'user_name',
                'type' => 'profile',
                'profile_key' => 'user_name',
                'custom_field_key' => null,
                'label_jpn' => '名前',
                'label_eng' => 'Name',
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'user_company',
                'type' => 'profile',
                'profile_key' => 'user_company',
                'custom_field_key' => null,
                'label_jpn' => '会社名',
                'label_eng' => 'Affiliation',
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'user_email',
                'type' => 'profile',
                'profile_key' => 'user_email',
                'custom_field_key' => null,
                'label_jpn' => 'メールアドレス',
                'label_eng' => 'Mail Address',
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'participation_attributes',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional17730219047681',
                'label_jpn' => '参加属性',
                'label_eng' => 'Participation Attributes',
                'sort_order' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'exhibitor_category',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional17730219139603',
                'label_jpn' => '出展カテゴリ',
                'label_eng' => 'Exhibitor Category',
                'sort_order' => 5,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'business_areas',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional17708818002051',
                'label_jpn' => '事業展開エリア',
                'label_eng' => 'Business Areas',
                'sort_order' => 6,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'industry',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional177088184133310',
                'label_jpn' => '業種',
                'label_eng' => 'Industry',
                'sort_order' => 7,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'occupation',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional177088218066886',
                'label_jpn' => '職種',
                'label_eng' => 'Occupation',
                'sort_order' => 8,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'customer_base',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional177088187191211',
                'label_jpn' => '顧客層',
                'label_eng' => 'Customer Base (B2B/B2C)',
                'sort_order' => 9,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'investment_round',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional1770882915797263',
                'label_jpn' => '投資ラウンド',
                'label_eng' => 'Investment round',
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'funding_target',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional1770883050540287',
                'label_jpn' => '希望調達額',
                'label_eng' => 'Funding Target',
                'sort_order' => 11,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'investable_amount',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional1770883113603306',
                'label_jpn' => '投資可能額',
                'label_eng' => 'Investable amount',
                'sort_order' => 12,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'sns_url_free_text',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional1770883184671331',
                'label_jpn' => 'SNS URL',
                'label_eng' => 'SNS URL',
                'sort_order' => 13,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'pr_free_text',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional1770883198831332',
                'label_jpn' => '自己PR',
                'label_eng' => 'PR',
                'sort_order' => 14,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'target_industry',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional1770883225643333',
                'label_jpn' => 'マッチング相手の希望業種',
                'label_eng' => 'Target Industry',
                'sort_order' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'what_i_am_looking_for',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional1770883364729378',
                'label_jpn' => '相手に期待すること',
                'label_eng' => 'What I am looking for.',
                'sort_order' => 16,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'column_key' => 'what_i_am_looking_for_free_text',
                'type' => 'custom',
                'profile_key' => null,
                'custom_field_key' => 'additional1770883457395408',
                'label_jpn' => '相手に期待すること（自由記述）',
                'label_eng' => 'What I am looking for（Free-text）',
                'sort_order' => 17,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_csv_download_columns');
    }
};
