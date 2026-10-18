<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\SongListKind;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Models\Dj\SongList;
use App\Models\Settings\DjSettings;
use App\Services\Dj\SongLists;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a guest pass to a setlist or a gig's requests, or changing one. A blank password is
 * generated when adding, and kept when changing.
 */
class GuestPassRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pass = $this->route('guestPass');

        if ($pass instanceof GuestPass) {
            return $this->user()?->can('update', $pass) ?? false;
        }

        $list = $this->route('songList');
        $gig = $this->route('gig');

        if ($list instanceof SongList) {
            return $this->user()?->can('create', [GuestPass::class, $list]) ?? false;
        }

        // A gig's requests list may not exist yet, so the check uses an unsaved one
        return $gig instanceof Gig
            && ($this->user()?->can('create', [GuestPass::class, new SongList(['kind' => SongListKind::Requests])]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $updating = $this->route('guestPass') instanceof GuestPass;

        return [
            'label' => ['required', Rule::string()->max(100)],
            'password' => ['nullable', Rule::string()->min(4)->max(100)],
            'regenerate_password' => [Rule::when($updating, ['nullable', 'boolean'], ['prohibited'])],
            'can_add' => ['required', 'boolean'],
            // The last day it works, on the DJ's clock
            'expires_on' => ['nullable', 'date_format:Y-m-d', Rule::when(! $updating, 'after_or_equal:today')],
            'revoked' => [Rule::when($updating, ['nullable', 'boolean'], ['prohibited'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'label' => __('dj.guest_passes.fields.label'),
            'password' => __('dj.guest_passes.fields.password'),
            'can_add' => __('dj.guest_passes.fields.can_add'),
            'expires_on' => __('dj.guest_passes.fields.expires_on'),
        ];
    }

    /**
     * The list the new pass is for: the route's song list, or the gig's requests list
     */
    public function songList(SongLists $songLists): SongList
    {
        $list = $this->route('songList');

        if ($list instanceof SongList) {
            return $list;
        }

        /** @var Gig $gig */
        $gig = $this->route('gig');

        return $songLists->requestsListFor($gig);
    }

    /**
     * The pass's attributes, leaving the password out when it isn't being changed
     *
     * @return array<string, mixed>
     */
    public function passAttributes(?string $generatedPassword = null): array
    {
        $password = trim((string) $this->validated('password'));
        $expiresOn = $this->validated('expires_on');

        $attributes = [
            'label' => $this->validated('label'),
            'can_add' => $this->boolean('can_add'),
            'expires_at' => $expiresOn === null
                ? null
                : CarbonImmutable::createFromFormat('Y-m-d', (string) $expiresOn, app(DjSettings::class)->default_timezone)?->endOfDay()->utc(),
        ];

        if ($password !== '') {
            $attributes['password'] = $password;
        } elseif ($generatedPassword !== null) {
            $attributes['password'] = $generatedPassword;
        }

        if ($this->has('revoked')) {
            $pass = $this->route('guestPass');
            // Revoking again keeps the first time it was revoked
            $attributes['revoked_at'] = $this->boolean('revoked')
                ? (($pass instanceof GuestPass ? $pass->revoked_at : null) ?? now())
                : null;
        }

        return $attributes;
    }
}
