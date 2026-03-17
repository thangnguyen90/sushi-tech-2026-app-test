<?php

namespace App\Http\Requests;

class MatchingPartnerIndexRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('type')) {
            $this->merge([
                'type' => strtolower((string) $this->input('type')),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'live_chat_data_source_id' => ['nullable', 'integer'],
            'language_id' => ['nullable', 'integer'],
            'limit_exhibitors' => ['nullable', 'integer', 'min:1', 'max:50'],
            'limit_visitors' => ['nullable', 'integer', 'min:1', 'max:50'],
            'limit_networking_per_name' => ['nullable', 'integer', 'min:1', 'max:50'],
            'type' => ['nullable', 'string', 'in:exhibitor,visitor'],
            'seed' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'keyword' => ['nullable', 'string'],

            // NEW: single selected option_value
            'option_values' => ['nullable', 'array'],
            'option_values.*' => ['string'],
        ];
    }
}
