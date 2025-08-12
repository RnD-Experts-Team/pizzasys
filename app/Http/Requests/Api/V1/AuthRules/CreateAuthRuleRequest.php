<?php

namespace App\Http\Requests\Api\V1\AuthRules;

use Illuminate\Foundation\Http\FormRequest;

class CreateAuthRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service' => 'required|string|max:255',
            'method' => 'required|string|in:GET,POST,PUT,PATCH,DELETE,ANY',
            'path_dsl' => 'required_without:route_name|nullable|string|max:255',
            'route_name' => 'required_without:path_dsl|nullable|string|max:255',
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
            'path_dsl.required_without' => 'Either path_dsl or route_name is required.',
            'route_name.required_without' => 'Either route_name or path_dsl is required.',
        ];
    }
}
