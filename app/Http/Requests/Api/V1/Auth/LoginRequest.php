<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required','email'],
            'password' => ['required','string'],

            // optional field to tell backend who is calling
            'client_type' => ['sometimes', 'string', Rule::in(['web','mobile'])],

            // device object (optional in general)
            'device' => ['sometimes','array'],
            'device.device_id' => ['sometimes','nullable','string','max:191'],
            'device.platform' => ['sometimes','nullable','string', Rule::in(['ios','android','web'])],
            'device.model' => ['sometimes','nullable','string','max:100'],
            'device.os_version' => ['sometimes','nullable','string','max:50'],
            'device.app_version' => ['sometimes','nullable','string','max:50'],

            // fcm optional in general
            'fcm_token' => ['sometimes','nullable','string','max:500'],
        ];
    }

    /**
     * Make some fields REQUIRED only when client_type=mobile
     */
    public function withValidator($validator)
    {
        $validator->sometimes(
            ['device.platform'],
            'required',
            fn ($input) => ($input->client_type ?? null) === 'mobile'
        );

        $validator->sometimes(
            ['fcm_token'],
            'required',
            fn ($input) => ($input->client_type ?? null) === 'mobile'
        );
    }
}
