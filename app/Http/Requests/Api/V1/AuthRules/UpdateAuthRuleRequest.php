<?php

namespace App\Http\Requests\Api\V1\AuthRules;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAuthRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service' => 'sometimes|string|max:255',
            'method' => 'sometimes|string|in:GET,POST,PUT,PATCH,DELETE,ANY',
            'path_dsl' => 'sometimes|nullable|string|max:255',
            'route_name' => 'sometimes|nullable|string|max:255',
            'roles_any' => 'sometimes|array',
            'roles_any.*' => 'string|exists:roles,name',
            'permissions_any' => 'sometimes|array',
            'permissions_any.*' => 'string|exists:permissions,name',
            'permissions_all' => 'sometimes|array',
            'permissions_all.*' => 'string|exists:permissions,name',
            'priority' => 'sometimes|integer|min:1|max:1000',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'method.in' => 'Method must be one of: GET, POST, PUT, PATCH, DELETE, or ANY.',
            'priority.min' => 'Priority must be at least 1.',
            'priority.max' => 'Priority cannot exceed 1000.',
            'roles_any.*.exists' => 'One or more specified roles do not exist.',
            'permissions_any.*.exists' => 'One or more specified permissions do not exist.',
            'permissions_all.*.exists' => 'One or more specified permissions do not exist.',
        ];
    }

    /**
     * Handle additional validation logic
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Ensure at least one target is provided
            if (!$this->path_dsl && !$this->route_name) {
                $validator->errors()->add(
                    'target', 
                    'Either path_dsl or route_name must be provided.'
                );
            }

            // Ensure both targets are not provided simultaneously
            if ($this->path_dsl && $this->route_name) {
                $validator->errors()->add(
                    'target', 
                    'Cannot specify both path_dsl and route_name. Choose one.'
                );
            }

            // Ensure at least one authorization requirement is specified
            $hasRoles = !empty($this->roles_any);
            $hasPermsAny = !empty($this->permissions_any);
            $hasPermsAll = !empty($this->permissions_all);

            if (!$hasRoles && !$hasPermsAny && !$hasPermsAll) {
                $validator->errors()->add(
                    'authorization', 
                    'At least one authorization requirement must be specified (roles_any, permissions_any, or permissions_all).'
                );
            }
        });
    }
}
