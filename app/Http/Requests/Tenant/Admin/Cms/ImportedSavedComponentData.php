<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\IsImportedData;
use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesSavedComponent;
use App\Models\Cms\SavedComponent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One imported saved component, put through the same rules as its admin form, including rich text
 * being rebuilt from its JSON
 */
class ImportedSavedComponentData extends FormRequest
{
    /** @use IsImportedData<SavedComponent> */
    use IsImportedData;

    use ValidatesSavedComponent;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->savedComponentRules($this->existing);
    }
}
