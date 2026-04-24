<?php

namespace App\Http\Requests;

class FreeReceptionCheckinRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'room_id' => $this->route('room_id'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'min:1'],
            'first_user_uuid' => ['required', 'uuid', 'different:second_user_uuid'],
            'second_user_uuid' => ['required', 'uuid', 'different:first_user_uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required' => '商談場所IDが不正です。',
            'room_id.integer' => '商談場所IDが不正です。',
            'room_id.min' => '商談場所IDが不正です。',
            'first_user_uuid.required' => 'QRコードが不正です',
            'first_user_uuid.uuid' => 'QRコードが不正です',
            'first_user_uuid.different' => '同じ来場者は追加できません',
            'second_user_uuid.required' => 'QRコードが不正です',
            'second_user_uuid.uuid' => 'QRコードが不正です',
            'second_user_uuid.different' => '同じ来場者は追加できません',
        ];
    }
}
