<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesSavedComponent;
use App\Models\Cms\SavedComponent;
use Illuminate\Foundation\Http\FormRequest;

class SavedComponentUpdateRequest extends FormRequest
{
    use ValidatesSavedComponent;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->savedComponent()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->savedComponentRules($this->savedComponent());
    }

    public function savedComponent(): SavedComponent
    {
        /** @var SavedComponent $savedComponent */
        $savedComponent = $this->route('saved_component');

        return $savedComponent;
    }
}
