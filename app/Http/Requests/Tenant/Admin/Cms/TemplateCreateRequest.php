<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Enums\Cms\TemplateType;
use App\Models\Cms\Template;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemplateCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Template::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // A link can start the new template as a type, e.g. from a field that needs a component
            'type' => ['nullable', Rule::enum(TemplateType::class)],
        ];
    }

    /**
     * The type the new template starts as, if the link asked for one
     */
    public function initialType(): ?TemplateType
    {
        return $this->enum('type', TemplateType::class);
    }
}
