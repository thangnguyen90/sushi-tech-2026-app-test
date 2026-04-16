<?php

namespace App\Http\Requests;

class ReceptionCheckinRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_code' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'qr_code.required' => 'QRコードを読み取ってください。',
            'qr_code.string' => 'QRコードを読み取ってください。',
        ];
    }
}
