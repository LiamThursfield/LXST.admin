<?php

namespace App\Http\Requests\Tenant\Admin\Tracker;

use App\Models\Tracker\Category;
use App\Services\Authorisation\Enums\TrackerPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class CategoryUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. The user must
     * be able to update the category, and turning it public (or keeping it
     * public) requires the dedicated public-category permission.
     */
    public function authorize(): bool
    {
        $category = $this->route('category');

        if (! ($this->user()?->can('update', $category) ?? false)) {
            return false;
        }

        if ($this->boolean('is_public')) {
            return $this->user()->can(TrackerPermission::ManagePublicCategories);
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80', $this->uniqueNameRule()],
            'colour' => ['nullable', 'string', 'max:30'],
            'icon' => ['nullable', 'string', 'max:60'],
            'is_public' => ['boolean'],
        ];
    }

    /**
     * Scoped uniqueness, ignoring the category being updated.
     */
    protected function uniqueNameRule(): Unique
    {
        /** @var Category $category */
        $category = $this->route('category');

        $rule = Rule::unique('tracker_categories', 'name')->ignore($category->id);

        if ($this->boolean('is_public')) {
            return $rule->where('is_public', true);
        }

        return $rule->where('is_public', false)
            ->where('user_id', $this->user()?->id);
    }
}
