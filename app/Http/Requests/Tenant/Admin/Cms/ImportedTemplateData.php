<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\IsImportedData;
use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesTemplate;
use App\Models\Cms\Template;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One imported template, put through the same rules as the template builder. It isn't a real request:
 * pasted JSON can't be trusted any more than a form, so it's checked in exactly the same way. Whether
 * the user can import is checked by the import's own request.
 */
class ImportedTemplateData extends FormRequest
{
    /** @use IsImportedData<Template> */
    use IsImportedData;

    use ValidatesTemplate;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->templateRules($this->existing);
    }
}
