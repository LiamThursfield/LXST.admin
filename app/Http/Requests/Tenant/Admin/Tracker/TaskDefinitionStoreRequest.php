<?php

namespace App\Http\Requests\Tenant\Admin\Tracker;

use App\Http\Requests\Tenant\Admin\Tracker\Concerns\ValidatesTaskDefinition;
use App\Models\Tracker\TaskDefinition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TaskDefinitionStoreRequest extends FormRequest
{
    use ValidatesTaskDefinition;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', TaskDefinition::class) ?? false;
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
