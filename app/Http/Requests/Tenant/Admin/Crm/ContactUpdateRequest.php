<?php

namespace App\Http\Requests\Tenant\Admin\Crm;

use App\Http\Requests\Tenant\Admin\Crm\Concerns\ValidatesContact;
use App\Models\Crm\Contact;
use Illuminate\Foundation\Http\FormRequest;

class ContactUpdateRequest extends FormRequest
{
    use ValidatesContact;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->contact()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->contactRules($this->contact());
    }

    protected function contact(): Contact
    {
        /** @var Contact $contact */
        $contact = $this->route('contact');

        return $contact;
    }
}
