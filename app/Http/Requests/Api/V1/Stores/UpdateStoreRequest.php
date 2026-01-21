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
        /**
         * IMPORTANT:
         * If your route is /stores/{store} and uses implicit binding,
         * $this->route('store') will be the Store model, so we ignore by its numeric id.
         */
        $store = $this->route('store');
        $ignoreId = is_object($store) ? (int) $store->id : (int) $store;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('stores', 'name')->ignore($ignoreId),
            ],
            'metadata' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
