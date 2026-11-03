<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\IsImportedData;
use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesPage;
use App\Models\Cms\Page;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One imported page, put through the same rules as the page editor, including rich text being rebuilt
 * from its JSON
 */
class ImportedPageData extends FormRequest
{
    /** @use IsImportedData<Page> */
    use IsImportedData;

    use ValidatesPage;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->pageRules();
    }

    protected function currentPage(): ?Page
    {
        return $this->existing;
    }
}
