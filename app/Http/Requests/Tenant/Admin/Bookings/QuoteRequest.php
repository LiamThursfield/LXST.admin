<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Http\Requests\Tenant\Admin\Bookings\Concerns\ValidatesDocumentLines;
use App\Services\Authorisation\Enums\BookingsPermission;
use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    use ValidatesDocumentLines;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(BookingsPermission::ManagePayments) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->lineRules(),
            'valid_until' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->lineAttributes();
    }
}
