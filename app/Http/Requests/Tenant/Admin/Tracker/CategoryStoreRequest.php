<?php

namespace App\Http\Requests\Tenant\Admin\Tracker;

use App\Models\Tracker\Category;
use App\Services\Authorisation\Enums\TrackerPermission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class CategoryStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. Publishing a
     * public category requires the dedicated public-category permission.
     */
    public function authorize(): bool
    {
        if ($this->boolean('is_public')) {
            return $this->user()?->can(TrackerPermission::ManagePublicCategories) ?? false;
        }

        return $this->user()?->can('create', Category::class) ?? false;
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
     * Scoped uniqueness: public names are unique across all public categories;
     * private names are unique within the current user's private categories.
     */
    protected function uniqueNameRule(): Unique
    {
        $rule = Rule::unique('tracker_categories', 'name');

        if ($this->boolean('is_public')) {
            return $rule->where('is_public', true);
        }

        return $rule->where('is_public', false)
            ->where('user_id', $this->user()?->id);
    }
}
