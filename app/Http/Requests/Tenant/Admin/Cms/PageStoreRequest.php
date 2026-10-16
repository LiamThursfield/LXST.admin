<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesPage;
use App\Models\Cms\Page;
use Illuminate\Foundation\Http\FormRequest;

class PageStoreRequest extends FormRequest
{
    use ValidatesPage;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Page::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->pageRules();
    }

    protected function currentPage(): ?Page
    {
        return null;
    }
}
