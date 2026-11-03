<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\IsImportedData;
use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesLayout;
use App\Models\Cms\Layout;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One imported layout, put through the same rules as its admin form, including rich text being rebuilt
 * from its JSON
 */
class ImportedLayoutData extends FormRequest
{
    /** @use IsImportedData<Layout> */
    use IsImportedData;

    use ValidatesLayout;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->layoutRules($this->existing);
    }
}
