<?php

namespace App\Http\Requests;

class MatchingPartnerIndexRequest extends BaseRequest
{
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
        ];
    }
}
