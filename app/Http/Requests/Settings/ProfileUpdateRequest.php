<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->profileRules($this->user()?->id);

        // Only tenant users have a timezone (it drives tracker day logic).
        if (tenant() !== null) {
            $rules['timezone'] = ['sometimes', 'required', 'string', 'timezone'];
        }

        return $rules;
    }
}
