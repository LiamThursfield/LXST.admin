<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\IsImportedData;
use App\Http\Requests\Tenant\Admin\Crm\Concerns\ValidatesForm;
use App\Models\Crm\Form;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One imported CRM form, put through the same rules as the form builder
 */
class ImportedFormData extends FormRequest
{
    /** @use IsImportedData<Form> */
    use IsImportedData;

    use ValidatesForm;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->formRules($this->existing);
    }
}
