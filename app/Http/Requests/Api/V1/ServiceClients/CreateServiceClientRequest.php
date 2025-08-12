<?php

namespace App\Http\Requests\Api\V1\ServiceClients;

use Illuminate\Foundation\Http\FormRequest;

class CreateServiceClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:service_clients,name',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'sometimes|nullable|date|after:now',
            'notes' => 'sometimes|nullable|string|max:512',
        ];
    }
}
