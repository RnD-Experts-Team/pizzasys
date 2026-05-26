<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('password') && $this->input('password') === '') {
            $normalized['password'] = null;
        }

        if ($this->has('password_confirmation') && $this->input('password_confirmation') === '') {
            $normalized['password_confirmation'] = null;
        }

        if ($this->has('remove_image')) {
            $normalized['remove_image'] = $this->boolean('remove_image');
        }

        if (!empty($normalized)) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'password' => 'nullable|string|min:8|confirmed',
            'image' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_image' => 'sometimes|boolean',
        ];
    }
}
