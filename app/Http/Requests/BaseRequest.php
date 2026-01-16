<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class BaseRequest extends FormRequest
{
    /**
     * Handle a failed validation attempt.
     *
     * @param  Validator  $validator
     * @return void
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        // Build error payload
        $error = $this->buildErrorResponse($validator);

        // Throw JSON response exception
        throw new HttpResponseException(
            response()->json(['result' => $error], 400)
        );
    }

    /**
     * Build error code and message from validator.
     *
     * @param  Validator  $validator
     * @return array{code: string, message: string}
     */
    private function buildErrorResponse(Validator $validator): array
    {
        // Determine config namespace for error codes
        $constantError = $this->errorCode ?? 'constants.ERROR_CODE';

        // Get all error messages and failed rules
        $messages = $validator->errors()->getMessages();
        $failed   = $validator->failed();

        foreach ($failed as $inputKey => $rules) {
            // Parse input key parts (supports nested keys)
            $parts      = explode('.', $inputKey);
            $baseKey    = array_shift($parts);
            $nestedKeys = $parts;

            // Get the first failed rule name in lowercase
            $rule = strtolower(array_key_first($rules));

            // Build config path for error code
            if (count($nestedKeys) >= 2) {
                // e.g. constants.ERROR_CODE.field.child.rule
                $child     = $nestedKeys[1];
                $configPath = sprintf("%s.%s.%s.%s", $constantError, $baseKey, $child, $rule);
            } else {
                // e.g. constants.ERROR_CODE.field.rule
                $configPath = sprintf("%s.%s.%s", $constantError, $baseKey, $rule);
            }

            // Resolve code from config or default to empty string
            $code = config($configPath, '');

            // Determine the error message: prefer full key, fallback to base key
            $message = $messages[$inputKey][0] ?? ($messages[$baseKey][0] ?? 'Validation error');

            return [
                'code'    => $code,
                'message' => $message,
            ];
        }

        // Fallback if no errors detected
        return [
            'code'    => '',
            'message' => 'Validation error',
        ];
    }
}
