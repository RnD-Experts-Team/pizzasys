<?php

namespace App\Http\Requests\Api\V1\ServiceClients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('service_clients')->ignore($this->serviceClient)
            ],
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'sometimes|nullable|date|after:now',
            'notes' => 'sometimes|nullable|string|max:512',
        ];
    }
}
