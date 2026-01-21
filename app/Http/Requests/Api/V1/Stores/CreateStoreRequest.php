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
            'store_id' => 'required|string|max:255|unique:stores,store_id',
            'name' => 'required|string|max:255|unique:stores,name',
            'metadata' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.unique' => 'A store with this store_id already exists.',
            'name.unique' => 'A store with this name already exists.',
        ];
    }
}
