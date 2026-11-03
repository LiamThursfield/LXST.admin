<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Models\Cms\SavedComponent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavedComponentExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SavedComponent::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', Rule::exists('cms_saved_components', 'id')],
        ];
    }
}
