<?php

namespace App\Repositories;

use App\Models\ChatProfileContent;
use Illuminate\Support\Collection;

class ChatProfileContentRepository extends BaseRepository
{
    protected function modelClass(): string
    {
        return ChatProfileContent::class;
    }

    /**
     * Return filter fields grouped by field_key with language-specific texts.
     *
     * @param  string  $lang  eng|jpn
     * @return array<int, array<string, mixed>>
     */
    public function getFilterFields(string $lang = 'jpn', bool $onlyEnabled = false): array
    {
        $lang = $this->normalizeLang($lang);

        $query = $this->query()
            ->select([
                'field_key',
                'option_value',
                'label_eng',
                'label_jpn',
                'language_setting',
                'sort_order',
                'is_enabled',
            ])
            ->orderBy('field_key')
            ->orderBy('sort_order');

        if ($onlyEnabled) {
            $query->where('is_enabled', 1);
        }

        /** @var Collection<int, ChatProfileContent> $rows */
        $rows = $query->get();

        $grouped = [];

        foreach ($rows as $row) {
            $fieldKey = (string) $row->field_key;

            if (! isset($grouped[$fieldKey])) {
                // language_setting is already casted to array in model
                $ls = is_array($row->language_setting) ? $row->language_setting : [];
                $fieldText = $this->pickFieldText($ls, $lang);

                $grouped[$fieldKey] = [
                    'field_key' => $fieldKey,
                    'is_enabled' => (bool) $row->is_enabled,
                    'label' => $fieldText['label'],
                    'description' => $fieldText['description'],
                    'options' => [],
                ];
            }

            $grouped[$fieldKey]['options'][] = [
                'value' => (string) $row->option_value,
                'label' => $this->pickOptionLabel($row, $lang),
                'sort_order' => (int) $row->sort_order,
                'is_enabled' => (bool) $row->is_enabled,
            ];
        }

        foreach ($grouped as $fieldKey => $field) {
            array_unshift($grouped[$fieldKey]['options'], [
                'value' => '',
                'label' => $this->pickDefaultOptionLabel($lang),
            ]);
        }

        return array_values($grouped);
    }

    private function normalizeLang(string $lang): string
    {
        $l = strtolower(trim($lang));

        return $l === 'eng' ? 'eng' : 'jpn';
    }

    /**
     * Pick field label/description from field-level language_setting.
     * No fallback: return only requested language.
     *
     * @param  array<string, mixed>  $languageSetting
     * @return array{label:string, description:string}
     */
    private function pickFieldText(array $languageSetting, string $lang): array
    {
        return [
            'label' => (string) ($languageSetting[$lang]['label'] ?? ''),
            'description' => (string) ($languageSetting[$lang]['description'] ?? ''),
        ];
    }

    private function pickOptionLabel(ChatProfileContent $row, string $lang): string
    {
        return $lang === 'eng'
            ? (string) ($row->label_eng ?? '')
            : (string) ($row->label_jpn ?? '');
    }

    private function pickDefaultOptionLabel(string $lang): string
    {
        return $lang === 'eng'
            ? 'Please select'
            : '選択してください';
    }
}
