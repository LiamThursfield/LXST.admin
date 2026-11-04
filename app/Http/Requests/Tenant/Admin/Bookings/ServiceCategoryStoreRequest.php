<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Models\Bookings\ServiceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceCategoryStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ServiceCategory::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique(ServiceCategory::class, 'name')],
            'order' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
