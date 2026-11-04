<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Enums\Bookings\TimeOffStatus;
use App\Enums\Bookings\TimeOffType;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\TimeOff;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimeOffStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', TimeOff::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request. Someone asking for their own time off can
     * only ask for it from today on; someone managing time off can also record what's already
     * happened, such as sickness.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resource_id' => ['required', 'integer', Rule::exists(BookableResource::class, 'id')->withoutTrashed(), $this->ownResourceRule()],
            'type' => ['required', Rule::enum(TimeOffType::class)],
            'starts_on' => ['required', 'date_format:Y-m-d', ...($this->canManage() ? [] : ['after_or_equal:today']), $this->noOverlapRule()],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function canManage(): bool
    {
        return $this->user()?->can('manage', TimeOff::class) ?? false;
    }

    protected function ownResourceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->canManage()) {
                return;
            }

            if (! BookableResource::query()->whereKey($value)->where('user_id', $this->user()?->id)->exists()) {
                $fail(__('bookings.time_off.validation.not_yours'));
            }
        };
    }

    /**
     * The same days can't be asked for twice, unless the earlier request was declined
     */
    protected function noOverlapRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $startsOn = (string) $this->input('starts_on');

            $overlaps = TimeOff::query()
                ->where('resource_id', $this->input('resource_id'))
                ->where('status', '!=', TimeOffStatus::Declined)
                ->overlapping($startsOn, (string) ($this->input('ends_on') ?: $startsOn))
                ->exists();

            if ($overlaps) {
                $fail(__('bookings.time_off.validation.overlap'));
            }
        };
    }
}
