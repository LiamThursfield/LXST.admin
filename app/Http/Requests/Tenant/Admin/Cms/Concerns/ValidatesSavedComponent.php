<?php

namespace App\Http\Requests\Tenant\Admin\Cms\Concerns;

use App\Enums\Cms\TemplateType;
use App\Models\Cms\SavedComponent;
use App\Models\Cms\Template;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesSavedComponent
{
    use ValidatesContent;

    protected ?Template $resolvedTemplate = null;

    /**
     * Fill in a blank slug from the name
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && is_string($this->input('name'))) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }

    /**
     * The shared validation rules for creating or updating a saved component, plus the chosen template's content rules
     *
     * @return array<string, mixed>
     */
    protected function savedComponentRules(?SavedComponent $savedComponent = null): array
    {
        return [
            'template_id' => [
                'required',
                'integer',
                Rule::exists('cms_templates', 'id')->where('type', TemplateType::Component->value),
            ],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', Rule::unique('cms_saved_components', 'slug')->ignore($savedComponent?->id)],
            ...$this->contentRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_id.exists' => __('cms.saved_components.validation.template_type'),
        ];
    }

    protected function contentTemplate(): ?Template
    {
        $id = $this->input('template_id');

        if (! is_numeric($id)) {
            return null;
        }

        return $this->resolvedTemplate ??= Template::query()
            ->where('type', TemplateType::Component)
            ->whereKey((int) $id)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function savedComponentAttributes(): array
    {
        return $this->safe()->only(['template_id', 'name', 'slug']);
    }
}
