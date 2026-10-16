<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Http\Requests\Tenant\Admin\Cms\Concerns\ValidatesLayout;
use App\Models\Cms\Layout;
use Illuminate\Foundation\Http\FormRequest;

class LayoutStoreRequest extends FormRequest
{
    use ValidatesLayout;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Layout::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->layoutRules();
    }
}
