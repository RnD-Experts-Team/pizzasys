<?php

namespace App\Http\Requests\Api\V1\Stores;

use Illuminate\Foundation\Http\FormRequest;

class CreateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255|unique:stores,id',
            'name' => 'required|string|max:255|unique:stores,name',
            'metadata' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'id.unique' => 'A store with this ID already exists.',
            'name.unique' => 'A store with this name already exists.',
        ];
    }
}
