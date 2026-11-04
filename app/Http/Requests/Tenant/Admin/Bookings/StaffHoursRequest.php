<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Services\Authorisation\Enums\BookingsPermission;
use Illuminate\Foundation\Http\FormRequest;

class StaffHoursRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(BookingsPermission::ViewStaffReports) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:'.$this->latestEnd()],
        ];
    }

    /**
     * A range is limited to a year, as every date in it is worked out for every person
     */
    protected function latestEnd(): string
    {
        $from = $this->input('from');

        return (is_string($from) && strtotime($from) !== false ? now()->parse($from) : now())->addYear()->toDateString();
    }
}
