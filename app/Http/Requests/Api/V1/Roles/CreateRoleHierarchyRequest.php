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
            'store_id' => 'required|integer|exists:stores,id',
            'metadata' => 'sometimes|array',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hierarchyService = app(RoleHierarchyService::class);

            $errors = $hierarchyService->validateHierarchy(
                (int) $this->higher_role_id,
                (int) $this->lower_role_id,
                (int) $this->store_id
            );

            foreach ($errors as $error) {
                $validator->errors()->add('hierarchy', $error);
            }
        });
    }
}
