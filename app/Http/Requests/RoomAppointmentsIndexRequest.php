<?php

namespace App\Http\Requests;

class RoomAppointmentsIndexRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'room_id' => $this->route('room_id'),
            'user_uuid' => $this->query('user_uuid'),
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
            'user_uuid' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required' => '商談場所IDが不正です。',
            'room_id.integer' => '商談場所IDが不正です。',
            'room_id.min' => '商談場所IDが不正です。',
            'user_uuid.required' => 'QRコードが不正です',
            'user_uuid.uuid' => 'QRコードが不正です',
        ];
    }
}
