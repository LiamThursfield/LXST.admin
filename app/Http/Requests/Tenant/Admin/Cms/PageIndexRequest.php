<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Enums\Cms\PublishStatus;
use App\Models\Cms\Page;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Page::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.search' => ['nullable', Rule::string()],
            'filter.status' => ['nullable', Rule::enum(PublishStatus::class)],
        ];
    }
}
