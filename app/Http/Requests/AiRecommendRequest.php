<?php

namespace App\Http\Requests;

class AiRecommendRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'user_uuid_list' => ['nullable', 'array'],
            'user_uuid_list.*' => ['string', 'uuid', 'distinct'],
        ];
    }
}
