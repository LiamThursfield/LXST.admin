<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Settings\DjSettings;
use App\Services\Authorisation\Enums\DjPermission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The period and plays The Annual Drop covers: a year (this one by default) or a date range, on the
 * DJ's clock, and gigs only or all history
 */
class AnnualDropRequest extends FormRequest
{
    public const string SCOPE_GIGS = 'gigs';

    public const string SCOPE_ALL = 'all';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(DjPermission::ViewStats) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'scope' => ['nullable', Rule::in([self::SCOPE_GIGS, self::SCOPE_ALL])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'year' => __('dj.annual_drop.fields.year'),
            'from' => __('dj.annual_drop.fields.from'),
            'to' => __('dj.annual_drop.fields.to'),
        ];
    }

    /**
     * The period's start and end (UTC), from the date range if given, otherwise the year
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(): array
    {
        $timezone = app(DjSettings::class)->default_timezone;

        if ($this->filled('from')) {
            return [
                CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated('from'), $timezone)?->startOfDay()->utc() ?? CarbonImmutable::now(),
                CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated('to'), $timezone)?->endOfDay()->utc() ?? CarbonImmutable::now(),
            ];
        }

        $year = CarbonImmutable::create($this->year(), 1, 1, 0, 0, 0, $timezone) ?? CarbonImmutable::now($timezone)->startOfYear();

        return [$year->startOfYear()->utc(), $year->endOfYear()->utc()];
    }

    public function year(): int
    {
        return $this->filled('year')
            ? (int) $this->validated('year')
            : CarbonImmutable::now(app(DjSettings::class)->default_timezone)->year;
    }

    public function scope(): string
    {
        return (string) ($this->validated('scope') ?? self::SCOPE_GIGS);
    }
}
