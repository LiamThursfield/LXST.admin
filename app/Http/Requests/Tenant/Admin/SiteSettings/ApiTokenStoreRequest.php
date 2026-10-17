<?php

namespace App\Http\Requests\Tenant\Admin\SiteSettings;

use App\Enums\Api\ApiTokenAbility;
use App\Models\ApiToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiTokenStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ApiToken::class) ?? false;
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
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'distinct', Rule::enum(ApiTokenAbility::class)],
            // The last day the token works, on the user's clock
            'expires_on' => ['nullable', Rule::date()->format('Y-m-d')->afterOrEqual($this->localToday())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('site_settings.api_tokens.fields.name'),
            'abilities' => __('site_settings.api_tokens.fields.abilities'),
            'abilities.*' => __('site_settings.api_tokens.fields.abilities'),
            'expires_on' => __('site_settings.api_tokens.fields.expires_on'),
        ];
    }

    /**
     * @return list<string>
     */
    public function abilities(): array
    {
        /** @var list<string> $abilities */
        $abilities = array_values(array_unique($this->validated('abilities')));

        return $abilities;
    }

    /**
     * The end of the chosen day on the user's clock, or null for a token that never expires
     */
    public function expiresAt(): ?CarbonImmutable
    {
        /** @var string|null $expiresOn */
        $expiresOn = $this->validated('expires_on');

        if ($expiresOn === null) {
            return null;
        }

        return $this->creator()->parseLocal($expiresOn.' 23:59:59');
    }

    public function creator(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }

    protected function localToday(): string
    {
        $user = $this->user();

        return ($user instanceof User ? $user->localNow() : CarbonImmutable::now())->toDateString();
    }
}
