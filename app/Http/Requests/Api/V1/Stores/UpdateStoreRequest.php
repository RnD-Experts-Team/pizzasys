<?php

namespace App\Http\Requests\Api\V1\Stores;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreRequest extends FormRequest
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
                Rule::unique('stores')->ignore($this->store)
            ],
            'metadata' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
