<?php

namespace App\Http\Requests;

class AppointmentCheckinRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'appointment_id' => $this->route('id'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'min:1'],
            'user_uuid' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => '商談IDが不正です。',
            'appointment_id.integer' => '商談IDが不正です。',
            'appointment_id.min' => '商談IDが不正です。',
            'user_uuid.required' => 'QRコードが不正です',
            'user_uuid.uuid' => 'QRコードが不正です',
        ];
    }
}
