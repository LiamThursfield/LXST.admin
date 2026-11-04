<?php

namespace App\Http\Requests\Tenant\Admin\SiteSettings;

use App\Enums\BookingType;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Industry\IndustryProfiles;
use App\Services\Industry\Terminology;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndustrySettingsUpdateRequest extends FormRequest
{
    /** The longest a term can be, as they're used in menus and headings */
    protected const int TERM_MAX_LENGTH = 50;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can(SettingsPermission::ManageSettings) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $profiles = app(IndustryProfiles::class);
        $termKeys = app(Terminology::class)->termKeys();

        return [
            'industry' => ['required', 'string', Rule::in($profiles->keys())],
            'terms' => ['present', 'array', 'array:'.implode(',', $termKeys)],
            'terms.*' => ['array:singular,plural'],
            'terms.*.singular' => ['nullable', 'string', 'max:'.self::TERM_MAX_LENGTH, 'required_with:terms.*.plural'],
            'terms.*.plural' => ['nullable', 'string', 'max:'.self::TERM_MAX_LENGTH, 'required_with:terms.*.singular'],
            'booking_types' => ['present', 'array'],
            'booking_types.*' => [Rule::enum(BookingType::class)],
            'customer_fields' => ['present', 'array'],
            'customer_fields.*' => [Rule::in(array_column($profiles->customerFieldItems(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms.*.singular.required_with' => __('site_settings.industry.validation.singular_required'),
            'terms.*.plural.required_with' => __('site_settings.industry.validation.plural_required'),
        ];
    }

    /**
     * What's different from the chosen industry's defaults, as only that is stored: a default that
     * changes later still reaches this tenant unless it changed that part
     *
     * @return array{terms?: array<string, array{singular: string, plural: string}>, booking_types?: list<string>, customer_fields?: list<string>}
     */
    public function overrides(): array
    {
        $profile = app(IndustryProfiles::class)->find($this->string('industry')->toString());
        $defaults = app(Terminology::class)->defaultsFor($profile->key);

        /** @var array<string, array{singular: string|null, plural: string|null}> $terms */
        $terms = $this->validated('terms');
        /** @var list<string> $bookingTypes */
        $bookingTypes = $this->validated('booking_types');
        /** @var list<string> $customerFields */
        $customerFields = $this->validated('customer_fields');

        $termOverrides = collect($terms)
            ->filter(fn (array $term) => filled($term['singular'] ?? null))
            ->reject(fn (array $term, string $key) => $term['singular'] === $defaults[$key]['singular'] && $term['plural'] === $defaults[$key]['plural'])
            ->all();

        $defaultBookingTypes = array_map(fn (BookingType $type) => $type->value, $profile->bookingTypes);

        /** @var array{terms?: array<string, array{singular: string, plural: string}>, booking_types?: list<string>, customer_fields?: list<string>} */
        return array_filter([
            'terms' => $termOverrides ?: null,
            'booking_types' => $this->listIfChanged($bookingTypes, $defaultBookingTypes),
            'customer_fields' => $this->listIfChanged($customerFields, $profile->customerFields),
        ], fn (?array $override) => $override !== null);
    }

    /**
     * @param  list<string>  $chosen
     * @param  list<string>  $defaults
     * @return list<string>|null
     */
    protected function listIfChanged(array $chosen, array $defaults): ?array
    {
        $chosen = array_values(array_unique($chosen));

        return array_diff($chosen, $defaults) === [] && array_diff($defaults, $chosen) === [] ? null : $chosen;
    }
}
