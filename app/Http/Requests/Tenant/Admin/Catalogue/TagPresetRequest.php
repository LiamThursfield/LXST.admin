<?php

namespace App\Http\Requests\Tenant\Admin\Catalogue;

use App\Models\Catalogue\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a group of built-in tags, such as the allergens
 */
class TagPresetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tag::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var array<string, mixed> $groups */
        $groups = config('catalogue.tag_presets');

        return [
            'group' => ['required', 'string', Rule::in(array_keys($groups))],
        ];
    }
}
