<?php

namespace App\Repositories;

use App\Models\LiveChatProfileFieldOption;
use Illuminate\Support\Facades\DB;

class LiveChatProfileFieldOptionRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return LiveChatProfileFieldOption::class;
    }

    /**
     * Fetch custom fields (selected options) for a single profile_id,
     * and resolve field meta + option label by joining chat_profile_contents.
     *
     * - Field meta: chat_profile_contents.language_setting (field-level, identical per field_key)
     * - Option label: chat_profile_contents.label_eng/label_jpn
     *
     * @return array<int, array<string, mixed>>
     */
    public function getResolvedCustomFields(int $profileId, string $lang = 'jpn'): array
    {
        $lang = $this->normalizeLang($lang);

        // IMPORTANT:
        // We join by (field_key, option_value) because option_value in field_options matches option_value in contents.
        // If your schema instead wants join by option_id, adjust accordingly.
        $rows = DB::table('live_chat_profile_field_options as fo')
            ->leftJoin('chat_profile_contents as c', function ($join) {
                $join->on('c.field_key', '=', 'fo.field_key')
                    ->on('c.option_value', '=', 'fo.option_value');
            })
            ->whereNull('fo.deleted_at')
            ->where('fo.profile_id', $profileId)
            ->orderBy('fo.field_key')
            ->orderBy('fo.id')
            ->get([
                'fo.field_key',
                'fo.option_id',
                'fo.option_value',
                'c.label_eng',
                'c.label_jpn',
                'c.language_setting',
            ]);

        // Group by field_key (support multi-select: multiple rows per field_key)
        $grouped = [];

        foreach ($rows as $r) {
            $fieldKey = (string) ($r->field_key ?? '');
            if ($fieldKey === '') {
                continue;
            }

            if (!isset($grouped[$fieldKey])) {
                $ls = is_array($r->language_setting) ? $r->language_setting : (json_decode((string) ($r->language_setting ?? ''), true) ?: []);
                $grouped[$fieldKey] = [
                    'field_key' => $fieldKey,
                    'label' => (string) ($ls[$lang]['label'] ?? ''),
                    'description' => (string) ($ls[$lang]['description'] ?? ''),
                    'values' => [],
                ];
            }

            $optionLabel = $lang === 'eng' ? (string) ($r->label_eng ?? '') : (string) ($r->label_jpn ?? '');

            $grouped[$fieldKey]['values'][] = [
                'option_id' => isset($r->option_id) ? (int) $r->option_id : null,
                'option_value' => (string) ($r->option_value ?? ''),
                'label' => $optionLabel,
            ];
        }

        return array_values($grouped);
    }

    private function normalizeLang(string $lang): string
    {
        $l = strtolower(trim($lang));
        return $l === 'eng' ? 'eng' : 'jpn';
    }
}
