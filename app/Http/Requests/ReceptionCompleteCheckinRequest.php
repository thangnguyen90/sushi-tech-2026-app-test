<?php

namespace App\Http\Requests;

class ReceptionCompleteCheckinRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => '商談IDを指定してください。',
            'appointment_id.string' => '商談IDを指定してください。',
        ];
    }
}
