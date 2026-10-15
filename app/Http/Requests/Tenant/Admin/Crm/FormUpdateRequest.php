<?php

namespace App\Http\Requests\Tenant\Admin\Crm;

use App\Http\Requests\Tenant\Admin\Crm\Concerns\ValidatesForm;
use App\Models\Crm\Form;
use Illuminate\Foundation\Http\FormRequest;

class FormUpdateRequest extends FormRequest
{
    use ValidatesForm;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->crmForm()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->formRules($this->crmForm());
    }

    protected function crmForm(): Form
    {
        /** @var Form $form */
        $form = $this->route('form');

        return $form;
    }
}
