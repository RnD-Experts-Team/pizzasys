<?php

namespace App\Http\Requests\Api\V1\Roles;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoleHierarchyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'higher_role_id' => 'required|integer|exists:roles,id',
            'lower_role_id' => 'required|integer|exists:roles,id|different:higher_role_id',
            'store_id' => 'required|string|exists:stores,id',
            'metadata' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->higher_role_id === $this->lower_role_id) {
                $validator->errors()->add('lower_role_id', 'A role cannot manage itself.');
            }
            
            // Check for circular hierarchy
            $existing = \App\Models\RoleHierarchy::where('higher_role_id', $this->lower_role_id)
                ->where('lower_role_id', $this->higher_role_id)
                ->where('store_id', $this->store_id)
                ->exists();
                
            if ($existing) {
                $validator->errors()->add('hierarchy', 'This would create a circular hierarchy.');
            }
        });
    }
}
