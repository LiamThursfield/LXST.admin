<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Models\Bookings\BookableResource;
use App\Support\TimePeriods;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RotaUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', BookableResource::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resource_id' => ['required', 'integer', Rule::exists(BookableResource::class, 'id')->withoutTrashed()],
            'date' => ['required', 'date_format:Y-m-d'],
            'shifts' => ['nullable', 'array', 'max:10', $this->shiftsRule()],
            'shifts.*.starts_at' => ['required', 'date_format:H:i'],
            'shifts.*.ends_at' => ['required', 'date_format:H:i'],
            'shifts.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'shifts.*.starts_at' => __('bookings.resources.fields.starts_at'),
            'shifts.*.ends_at' => __('bookings.resources.fields.ends_at'),
            'shifts.*.note' => __('bookings.rota.fields.note'),
        ];
    }

    /**
     * A date's shifts can't overlap
     */
    protected function shiftsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $problem = TimePeriods::problem(is_array($value) ? $value : [], 'starts_at', 'ends_at');

            if ($problem !== null) {
                $fail(__("bookings.schedule.validation.{$problem}", ['when' => __('bookings.schedule.this_date')]));
            }
        };
    }
}
