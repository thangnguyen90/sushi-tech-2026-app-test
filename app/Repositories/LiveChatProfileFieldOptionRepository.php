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

        $rows = DB::table('live_chat_profile_field_options as fo')
            ->leftJoin('chat_profile_contents as c_option', function ($join) {
                $join->on('c_option.field_key', '=', 'fo.field_key')
                    ->on('c_option.option_value', '=', 'fo.option_value')
                    ->whereNull('c_option.deleted_at');
            })
            ->whereNull('fo.deleted_at')
            ->where('fo.profile_id', $profileId)
            ->orderBy('fo.field_key')
            ->orderBy('c_option.sort_order')
            ->orderBy('fo.id')
            ->get([
                'fo.field_key',
                'fo.option_id',
                'fo.option_value',
                'c_option.label_eng',
                'c_option.label_jpn',
                'c_option.sort_order',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $fieldMetaByKey = $this->loadFieldMetaByFieldKey(
            $rows->pluck('field_key')
                ->map(fn (mixed $fieldKey): string => trim((string) $fieldKey))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            $lang
        );

        $grouped = [];

        foreach ($rows as $r) {
            $fieldKey = (string) ($r->field_key ?? '');
            if ($fieldKey === '') {
                continue;
            }

            if (! isset($grouped[$fieldKey])) {
                $fieldMeta = $fieldMetaByKey[$fieldKey] ?? ['label' => '', 'description' => '', 'sort_order' => 999999];

                $grouped[$fieldKey] = [
                    'field_key' => $fieldKey,
                    'label' => $fieldMeta['label'],
                    'description' => $fieldMeta['description'],
                    'sort_order' => $fieldMeta['sort_order'],
                    'values' => [],
                ];
            }

            $optionLabel = $lang === 'eng' ? (string) ($r->label_eng ?? '') : (string) ($r->label_jpn ?? '');
            if ($optionLabel === '') {
                $optionLabel = (string) ($r->option_value ?? '');
            }

            $grouped[$fieldKey]['values'][] = [
                'option_value' => (string) ($r->option_value ?? ''),
                'label' => $optionLabel,
                'sort_order' => (int) ($r->sort_order ?? 999999),
            ];
        }

        $result = array_values($grouped);

        usort($result, function (array $a, array $b): int {
            return $a['sort_order'] <=> $b['sort_order'];
        });

        foreach ($result as &$field) {
            unset($field['sort_order']);

            usort($field['values'], function (array $a, array $b): int {
                return $a['sort_order'] <=> $b['sort_order'];
            });

            foreach ($field['values'] as &$val) {
                unset($val['sort_order']);
            }
            unset($val);
        }
        unset($field);

        return $result;
    }

    private function normalizeLang(string $lang): string
    {
        $l = strtolower(trim($lang));

        return $l === 'eng' ? 'eng' : 'jpn';
    }

    /**
     * @param  array<int, string>  $fieldKeys
     * @return array<string, array{label:string,description:string}>
     */
    private function loadFieldMetaByFieldKey(array $fieldKeys, string $lang): array
    {
        if ($fieldKeys === []) {
            return [];
        }

        $rows = DB::table('chat_profile_contents')
            ->whereNull('deleted_at')
            ->whereIn('field_key', $fieldKeys)
            ->orderBy('field_key')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get([
                'field_key',
                'language_setting',
                'sort_order',
            ]);

        $metaByKey = [];

        foreach ($rows as $row) {
            $fieldKey = trim((string) ($row->field_key ?? ''));
            if ($fieldKey === '' || isset($metaByKey[$fieldKey])) {
                continue;
            }

            $languageSetting = $this->decodeLanguageSetting($row->language_setting ?? null);

            $metaByKey[$fieldKey] = [
                'label' => (string) ($languageSetting[$lang]['label'] ?? ''),
                'description' => (string) ($languageSetting[$lang]['description'] ?? ''),
                'sort_order' => (int) ($row->sort_order ?? 999999),
            ];
        }

        return $metaByKey;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeLanguageSetting(mixed $languageSetting): array
    {
        if (is_array($languageSetting)) {
            return $languageSetting;
        }

        if (is_string($languageSetting)) {
            $decoded = json_decode($languageSetting, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
