<?php

namespace App\Repositories;

use App\Models\LiveChatProfileFieldOption;
use Illuminate\Support\Facades\DB;

class LiveChatProfileFieldOptionRepository extends BaseRepository
{
    private const int MAX_OPTION_VALUE_LENGTH = 500;

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
    public function getResolvedCustomFields(int $profileId, string $lang = 'jpn', ?array $profileCustomFields = null): array
    {
        $lang = $this->normalizeLang($lang);
        $profileCustomFields = $this->normalizeProfileCustomFields($profileCustomFields);

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
                'c_option.id as c_option_id',
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

            $resolvedOptionValue = $this->resolveOptionValue($r, $profileCustomFields);
            if ($resolvedOptionValue === null) {
                continue;
            }

            if (! isset($grouped[$fieldKey])) {
                $fieldMeta = $fieldMetaByKey[$fieldKey] ?? ['label' => '', 'description' => '', 'sort_order' => 999999, 'is_free_text' => false];

                $grouped[$fieldKey] = [
                    'field_key' => $fieldKey,
                    'label' => $fieldMeta['label'],
                    'description' => $fieldMeta['description'],
                    'sort_order' => $fieldMeta['sort_order'],
                    'values' => [],
                ];
            }

            $isFreeText = $fieldMetaByKey[$fieldKey]['is_free_text'] ?? false;
            $isOptionFound = ! is_null($r->c_option_id ?? null);
            $storedOptionValue = $this->normalizeScalarString($r->option_value ?? null);
            $shouldUseRawValue = $this->shouldUseRawValue(
                $fieldKey,
                $resolvedOptionValue,
                $isFreeText,
                $isOptionFound
            );
            $optionLabel = $lang === 'eng' ? (string) ($r->label_eng ?? '') : (string) ($r->label_jpn ?? '');
            if ($optionLabel === '') {
                $optionLabel = $resolvedOptionValue ?? '';
            }

            if ($shouldUseRawValue) {
                $optionLabel = $resolvedOptionValue;
            } elseif (! $isOptionFound) {
                // Dropdown/checkbox field, but option not found (deleted/missing)
                $optionLabel = null;
            }

            $grouped[$fieldKey]['values'][] = [
                'option_value' => $resolvedOptionValue ?? '',
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
                'option_value',
            ]);

        $metaByKey = [];

        foreach ($rows as $row) {
            $fieldKey = trim((string) ($row->field_key ?? ''));
            if ($fieldKey === '') {
                continue;
            }

            $isFreeText = ($row->option_value === '__free_text__');

            if (! isset($metaByKey[$fieldKey])) {
                $languageSetting = $this->decodeLanguageSetting($row->language_setting ?? null);

                $metaByKey[$fieldKey] = [
                    'label' => (string) ($languageSetting[$lang]['label'] ?? ''),
                    'description' => (string) ($languageSetting[$lang]['description'] ?? ''),
                    'sort_order' => (int) ($row->sort_order ?? 999999),
                    'is_free_text' => $isFreeText,
                ];
            } else {
                if ($isFreeText) {
                    $metaByKey[$fieldKey]['is_free_text'] = true;
                }
            }
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

    private function resolveOptionValue(object $row, array $profileCustomFields): ?string
    {
        $fieldKey = trim((string) ($row->field_key ?? ''));
        $rowOptionValue = $this->normalizeScalarString($row->option_value ?? null);
        if ($rowOptionValue !== null) {
            return $rowOptionValue;
        }

        if (! array_key_exists($fieldKey, $profileCustomFields)) {
            return null;
        }

        $profileOptionValue = $this->normalizeScalarString($profileCustomFields[$fieldKey]);
        if ($profileOptionValue === null) {
            return null;
        }

        if (mb_strlen($profileOptionValue) <= self::MAX_OPTION_VALUE_LENGTH) {
            return null;
        }

        return $profileOptionValue;
    }

    private function shouldUseRawValue(
        string $fieldKey,
        ?string $resolvedOptionValue,
        bool $isFreeText,
        bool $isOptionFound
    ): bool {
        if ($resolvedOptionValue === null) {
            return false;
        }

        if ($isFreeText) {
            return true;
        }

        return str_starts_with(strtolower($fieldKey), 'additional')
            && ! $isOptionFound
            && ! $this->isOptionLikeValue($resolvedOptionValue);
    }

    private function isOptionLikeValue(string $optionValue): bool
    {
        return str_starts_with(strtolower($optionValue), 'option');
    }

    private function normalizeProfileCustomFields(?array $profileCustomFields): array
    {
        if (! is_array($profileCustomFields)) {
            return [];
        }

        return $profileCustomFields;
    }

    private function normalizeScalarString(mixed $value): ?string
    {
        if (is_array($value) || is_object($value) || $value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || strcasecmp($normalized, 'null') === 0) {
            return null;
        }

        return $normalized;
    }
}
