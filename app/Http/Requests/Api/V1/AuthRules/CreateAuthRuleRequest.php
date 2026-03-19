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
            'store_scope_mode' => 'required|string|in:none,scoped,all_stores',
            'store_id_sources' => 'required_if:store_scope_mode,scoped|nullable|array',
            'store_match_policy' => 'required_if:store_scope_mode,scoped|nullable|string|in:all,any',
            'store_allows_empty' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'path_dsl.required_without' => 'Either path_dsl or route_name is required.',
            'route_name.required_without' => 'Either route_name or path_dsl is required.',
            'store_scope_mode.required' => 'The store_scope_mode is required for scoped rules.',
            'store_scope_mode.in' => 'The store_scope_mode must be one of: none, scoped, all_stores.',
            'store_id_sources.required_if' => 'The store_id_sources is required if store_scope_mode is scoped.',
            'store_match_policy.required_if' => 'The store_match_policy is required if store_scope_mode is scoped.',
            'store_match_policy.in' => 'The store_match_policy must be one of: all, any.',
        ];
    }
}