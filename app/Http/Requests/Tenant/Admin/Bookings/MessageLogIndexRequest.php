<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\Bookings\MessageStatus;
use App\Services\Authorisation\Enums\BookingsPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MessageLogIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(BookingsPermission::ViewBookings) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.status' => ['nullable', Rule::enum(MessageStatus::class)],
        ];
    }
}
