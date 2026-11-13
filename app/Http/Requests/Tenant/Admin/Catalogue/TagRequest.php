<?php

namespace App\Http\Requests\Tenant\Admin\Catalogue;

use App\Enums\Catalogue\TagKind;
use App\Models\Catalogue\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or changing one of the tenant's catalogue tags
 */
class TagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tag = $this->route('tag');

        return $tag instanceof Tag
            ? $this->user()?->can('update', $tag) ?? false
            : $this->user()?->can('create', Tag::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var list<string> $icons */
        $icons = config('catalogue.tag_icons');

        return [
            'name' => ['required', 'string', 'max:60'],
            'kind' => ['required', Rule::enum(TagKind::class)],
            'icon' => ['nullable', 'string', Rule::in($icons)],
            'order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array{name: string, kind: string, icon: string|null, order: int}
     */
    public function tagAttributes(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'kind' => $this->string('kind')->toString(),
            'icon' => $this->filled('icon') ? $this->string('icon')->toString() : null,
            'order' => $this->integer('order'),
        ];
    }
}
