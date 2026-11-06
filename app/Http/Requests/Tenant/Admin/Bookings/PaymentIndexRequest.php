<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\Bookings\PaymentMethod;
use App\Enums\Bookings\PaymentPurpose;
use App\Enums\Bookings\PaymentStatus;
use App\Services\Authorisation\Enums\BookingsPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(BookingsPermission::ViewPayments) ?? false;
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
            'filter.status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'filter.method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'filter.purpose' => ['nullable', Rule::enum(PaymentPurpose::class)],
        ];
    }
}
