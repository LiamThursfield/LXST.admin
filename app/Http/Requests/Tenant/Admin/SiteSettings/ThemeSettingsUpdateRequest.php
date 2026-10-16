<?php

namespace App\Http\Requests\Tenant\Admin\SiteSettings;

use App\Models\FileManager\File;
use App\Models\Settings\ThemeSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ThemeSettingsUpdateRequest extends FormRequest
{
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
        $image = Rule::exists(File::class, 'id')->where(fn (Builder $query) => $query->where('mime_type', 'like', 'image/%'));

        return [
            'logo_file_id' => ['nullable', 'integer', $image],
            'favicon_file_id' => ['nullable', 'integer', $image],
            'primary_color' => ['required', Rule::in(ThemeSettings::PRIMARY_PALETTES)],
            'neutral_color' => ['required', Rule::in(ThemeSettings::NEUTRAL_PALETTES)],
            'site_colors' => ['required', 'array:'.implode(',', array_keys(ThemeSettings::DEFAULT_SITE_COLORS))],
            'site_colors.*' => ['nullable', 'string', 'regex:/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo_file_id.exists' => __('site_settings.theme.validation.image'),
            'favicon_file_id.exists' => __('site_settings.theme.validation.image'),
            'site_colors.*.regex' => __('site_settings.theme.validation.hex'),
        ];
    }

    /**
     * Every site colour, upper-cased, with any that weren't sent left to the site (null)
     *
     * @return array<string, string|null>
     */
    public function siteColors(): array
    {
        /** @var array<string, string|null> $sent */
        $sent = $this->validated('site_colors');

        $colors = [];

        foreach (array_keys(ThemeSettings::DEFAULT_SITE_COLORS) as $key) {
            $colors[$key] = filled($sent[$key] ?? null) ? strtoupper((string) $sent[$key]) : null;
        }

        return $colors;
    }
}
