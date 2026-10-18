<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Dj\Gig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GigIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Gig::class) ?? false;
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
            'filter.search' => ['nullable', Rule::string()->max(255)],
            // Upcoming (and on now) by default
            'filter.when' => ['nullable', Rule::in(['upcoming', 'past', 'all'])],
        ];
    }

    public function period(): string
    {
        return (string) ($this->validated('filter.when') ?? 'upcoming');
    }
}
