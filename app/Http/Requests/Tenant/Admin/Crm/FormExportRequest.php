<?php

namespace App\Http\Requests\Tenant\Admin\Crm;

use App\Models\Crm\Form;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Form::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', Rule::exists('crm_forms', 'id')->whereNull('deleted_at')],
        ];
    }
}
