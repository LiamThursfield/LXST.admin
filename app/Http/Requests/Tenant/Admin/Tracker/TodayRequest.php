<?php

namespace App\Http\Requests\Tenant\Admin\Tracker;

use App\Models\Tracker\TaskDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TodayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TaskDefinition::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Past days can be reviewed and backfilled; the future cannot be logged.
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * The day being viewed, defaulting to today.
     */
    public function day(): CarbonImmutable
    {
        return $this->filled('date')
            ? CarbonImmutable::parse($this->string('date')->value())->startOfDay()
            : CarbonImmutable::today();
    }
}
