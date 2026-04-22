<?php

namespace App\Http\Requests;

class MatchingPartnerCsvDownloadRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('language')) {
            $payload['language'] = strtolower((string) $this->input('language'));
        }

        if ($this->has('uuid')) {
            $payload['uuid'] = trim((string) $this->input('uuid'));
        }

        if (! $this->has('live_chat_user_uuids') && ($payload['uuid'] ?? '') !== '') {
            $payload['live_chat_user_uuids'] = [$payload['uuid']];
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'live_chat_user_uuids' => ['nullable', 'array', 'min:1', 'max:500'],
            'live_chat_user_uuids.*' => ['required', 'uuid'],
            'language' => ['nullable', 'string', 'in:jpn,eng'],
        ];
    }

    public function messages(): array
    {
        return [
            'uuid.required' => 'uuid is required.',
            'uuid.uuid' => 'uuid must be a valid UUID.',
            'live_chat_user_uuids.array' => 'live_chat_user_uuids must be an array.',
            'live_chat_user_uuids.min' => 'live_chat_user_uuids must contain at least one item.',
            'live_chat_user_uuids.*.required' => 'Each live_chat_user_uuid is required.',
            'live_chat_user_uuids.*.uuid' => 'Each live_chat_user_uuid must be a valid UUID.',
            'language.in' => 'language must be jpn or eng.',
        ];
    }
}
