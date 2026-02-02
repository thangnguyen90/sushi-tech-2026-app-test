<?php

namespace App\Http\Requests;

class MarkMatchingRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'peer_user_id' => ['required', 'array'],
            'status' => ['required', 'integer', 'in:1,2,3,4'],
        ];
    }
}
