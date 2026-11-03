<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\IsImportedData;
use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesMenu;
use App\Models\Cms\Menu;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One imported menu, put through the same rules as the menu editor
 */
class ImportedMenuData extends FormRequest
{
    /** @use IsImportedData<Menu> */
    use IsImportedData;

    use ValidatesMenu;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->menuRules($this->existing);
    }
}
