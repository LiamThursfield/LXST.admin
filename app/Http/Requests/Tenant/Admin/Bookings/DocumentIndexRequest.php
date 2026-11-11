<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Services\Authorisation\Enums\BookingsPermission;
use Illuminate\Foundation\Http\FormRequest;

class DocumentIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(BookingsPermission::ViewPayments) ?? false;
    }

    /**
     * Get the validation rules that apply to the request. Statuses are checked by each list.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.status' => ['nullable', 'string', 'max:20'],
            'filter.search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
