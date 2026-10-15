<?php

namespace App\Http\Requests\Tenant\Admin\Crm;

use App\Http\Requests\Tenant\Admin\Crm\Concerns\ValidatesContact;
use App\Models\Crm\Contact;
use Illuminate\Foundation\Http\FormRequest;

class ContactStoreRequest extends FormRequest
{
    use ValidatesContact;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contact::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->contactRules();
    }
}
