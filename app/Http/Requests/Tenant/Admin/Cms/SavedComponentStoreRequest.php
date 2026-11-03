<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesSavedComponent;
use App\Models\Cms\SavedComponent;
use Illuminate\Foundation\Http\FormRequest;

class SavedComponentStoreRequest extends FormRequest
{
    use ValidatesSavedComponent;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', SavedComponent::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->savedComponentRules();
    }
}
