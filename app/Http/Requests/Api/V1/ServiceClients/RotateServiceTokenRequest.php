<?php

namespace App\Http\Requests\Api\V1\ServiceClients;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class RotateServiceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expires_at' => 'sometimes|nullable|date|after:now',
            'never_expires' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:512',
        ];
    }

    public function messages(): array
    {
        return [
            'expires_at.after' => 'Expiry date must be in the future.',
            'expires_at.date' => 'Expiry date must be a valid date format.',
            'notes.max' => 'Notes cannot exceed 512 characters.',
        ];
    }

    /**
     * Handle additional validation logic
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // If never_expires is true, expires_at should not be provided
            if ($this->boolean('never_expires') && $this->filled('expires_at')) {
                $validator->errors()->add(
                    'expires_at', 
                    'Cannot set expiry date when never_expires is true.'
                );
            }
        });
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation()
    {
        // If never_expires is true, remove expires_at
        if ($this->boolean('never_expires')) {
            $this->merge([
                'expires_at' => null
            ]);
        }
    }

    /**
     * Get validated data with processed expiry
     */
    public function getProcessedData(): array
    {
        $data = $this->validated();
        
        // Process expires_at to end of day if provided
        if (isset($data['expires_at']) && $data['expires_at']) {
            $data['expires_at'] = Carbon::parse($data['expires_at'])->endOfDay();
        }
        
        // Remove never_expires from final data as it's just a helper
        unset($data['never_expires']);
        
        return $data;
    }
}
