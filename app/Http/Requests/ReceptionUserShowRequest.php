<?php

namespace App\Http\Requests;

class ReceptionUserShowRequest extends BaseRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_uuid' => $this->route('user_uuid'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_uuid.required' => 'QRコードが不正です',
            'user_uuid.uuid' => 'QRコードが不正です',
        ];
    }
}
