<?php

namespace App\Http\Requests\Tenant\Admin\Tracker;

use App\Http\Requests\Tenant\Admin\Tracker\Concerns\ValidatesTaskDefinition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TaskDefinitionUpdateRequest extends FormRequest
{
    use ValidatesTaskDefinition;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('task')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->taskDefinitionRules();
    }
}
