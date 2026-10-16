<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Models\Cms\Redirect;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RedirectStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Redirect::class) ?? false;
    }

    /**
     * The redirect being edited, so it's ignored by the unique and chain checks
     */
    protected function currentRedirect(): ?Redirect
    {
        return null;
    }

    /**
     * Tidy the URLs before validating, so e.g. "old-page/" and "/old-page" count as the same
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'url_from' => is_string($this->input('url_from')) && $this->input('url_from') !== ''
                ? Redirect::normalisePath($this->input('url_from'))
                : $this->input('url_from'),
            'url_to' => is_string($this->input('url_to')) && $this->input('url_to') !== ''
                ? Redirect::normaliseDestination($this->input('url_to'))
                : $this->input('url_to'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Chains (A → B → C) and loops (A → B → A) are refused: a redirect can't start where another
     * one ends, or end where another one starts.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ignoreId = $this->currentRedirect()?->id;

        return [
            'url_from' => [
                'required',
                'string',
                'max:255',
                'different:url_to',
                Rule::unique(Redirect::class, 'url_from')->ignore($ignoreId),
                function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
                    $pointsHere = Redirect::query()
                        ->where('url_to', $value)
                        ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                        ->value('url_from');

                    if ($pointsHere !== null) {
                        $fail(__('cms.redirects.validation.from_is_destination', ['url' => $pointsHere]));
                    }
                },
            ],
            'url_to' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($ignoreId): void {
                    if (Redirect::isExternal((string) $value)) {
                        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                            $fail(__('cms.redirects.validation.to_invalid'));
                        }

                        return;
                    }

                    $redirectedOnwards = Redirect::query()
                        ->where('url_from', $value)
                        ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                        ->exists();

                    if ($redirectedOnwards) {
                        $fail(__('cms.redirects.validation.to_is_redirected', ['url' => $value]));
                    }
                },
            ],
            'is_enabled' => ['required', 'boolean'],
            'is_permanent' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'expired_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:published_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url_from.unique' => __('cms.redirects.validation.from_taken'),
            'url_from.different' => __('cms.redirects.validation.same_url'),
            'expired_at.after' => __('cms.redirects.validation.expires_before_publish'),
        ];
    }

    /**
     * The validated attributes, with dates entered on the user's clock stored as UTC
     *
     * @return array<string, mixed>
     */
    public function redirectAttributes(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'url_from' => $this->validated('url_from'),
            'url_to' => $this->validated('url_to'),
            'is_enabled' => $this->boolean('is_enabled'),
            'is_permanent' => $this->boolean('is_permanent'),
            'published_at' => $this->filled('published_at') ? $user->parseLocal($this->string('published_at')->toString()) : null,
            'expired_at' => $this->filled('expired_at') ? $user->parseLocal($this->string('expired_at')->toString()) : null,
        ];
    }
}
