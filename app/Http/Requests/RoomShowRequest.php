<?php

namespace App\Http\Requests;

class RoomShowRequest extends BaseRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'room_id.required' => '商談場所IDが不正です。',
            'room_id.integer' => '商談場所IDが不正です。',
            'room_id.min' => '商談場所IDが不正です。',
        ];
    }
}
