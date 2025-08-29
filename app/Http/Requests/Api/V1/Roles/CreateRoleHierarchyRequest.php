<?php

namespace App\Http\Requests\Api\V1\Roles;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\V1\Roles\RoleHierarchyService;

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
            // Use the comprehensive validation from the service
            $hierarchyService = app(RoleHierarchyService::class);
            
            $errors = $hierarchyService->validateHierarchy(
                $this->higher_role_id,
                $this->lower_role_id,
                $this->store_id
            );

            foreach ($errors as $error) {
                $validator->errors()->add('hierarchy', $error);
            }
        });
    }
}
