<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesPage;
use App\Models\Cms\Page;
use Illuminate\Foundation\Http\FormRequest;

class PageUpdateRequest extends FormRequest
{
    use ValidatesPage;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->currentPage()) ?? false;
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

    /**
     * Nullable to match the trait, though there's always a page when updating
     */
    protected function currentPage(): ?Page
    {
        /** @var Page $page */
        $page = $this->route('page');

        return $page;
    }
}
