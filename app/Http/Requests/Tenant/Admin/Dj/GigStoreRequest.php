<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Data\Dj\GigRequestSettings;
use App\Enums\Dj\RequestsMode;
use App\Models\Dj\Gig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A gig's details, with its times entered on the gig's own clock, and the request settings it
 * overrides (null fields use the DJ settings defaults)
 */
class GigStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Gig::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', Rule::string()->max(255)],
            'venue' => ['nullable', Rule::string()->max(255)],
            'location' => ['nullable', Rule::string()->max(255)],
            'timezone' => ['required', 'timezone:all'],
            // On the gig's clock, as datetime-local inputs send them
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:starts_at'],
            'notes' => ['nullable', Rule::string()->max(5000)],
            'request_settings' => ['nullable', 'array'],
            'request_settings.requests_mode' => ['nullable', Rule::enum(RequestsMode::class)],
            'request_settings.requests_open_minutes_before' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'request_settings.max_requests_per_visitor' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'request_settings.max_pending_requests' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'request_settings.allow_custom_requests' => ['nullable', 'boolean'],
            'request_settings.show_public_list' => ['nullable', 'boolean'],
            'request_settings.allow_upvotes' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('dj.gigs.fields.name'),
            'venue' => __('dj.gigs.fields.venue'),
            'location' => __('dj.gigs.fields.location'),
            'timezone' => __('dj.gigs.fields.timezone'),
            'starts_at' => __('dj.gigs.fields.starts_at'),
            'ends_at' => __('dj.gigs.fields.ends_at'),
            'notes' => __('dj.gigs.fields.notes'),
            'request_settings.requests_open_minutes_before' => __('dj.request_settings.fields.requests_open_minutes_before'),
            'request_settings.max_requests_per_visitor' => __('dj.request_settings.fields.max_requests_per_visitor'),
            'request_settings.max_pending_requests' => __('dj.request_settings.fields.max_pending_requests'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function gigAttributes(): array
    {
        /** @var string $timezone */
        $timezone = $this->validated('timezone');

        /** @var array<string, mixed> $overrides */
        $overrides = array_filter(
            (array) ($this->validated('request_settings') ?? []),
            fn (mixed $value) => $value !== null && $value !== '',
        );

        return [
            'name' => $this->validated('name'),
            'venue' => $this->validated('venue'),
            'location' => $this->validated('location'),
            'timezone' => $timezone,
            'starts_at' => $this->instant('starts_at', $timezone),
            'ends_at' => $this->instant('ends_at', $timezone),
            'notes' => $this->validated('notes'),
            'request_settings' => $overrides === [] ? null : GigRequestSettings::from($overrides),
        ];
    }

    /**
     * A time on the gig's clock, as an instant in UTC
     */
    protected function instant(string $field, string $timezone): CarbonImmutable
    {
        // Validated as this format, so it always parses
        return CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $this->validated($field), $timezone)?->utc()
            ?? CarbonImmutable::parse((string) $this->validated($field), $timezone)->utc();
    }
}
