<?php

namespace App\Http\Requests\Tenant\Api\V1\Dj;

use App\Enums\Dj\SetlistSongType;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\GuestPass;
use App\Models\Dj\SongListItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A guest adding a song to a setlist (from the library, or a custom one), or changing a song their
 * pass added. Only songs that can be requested can be picked from the library.
 */
class GuestSetlistItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pass = $this->guestPass();
        $item = $this->route('item');

        if ($item instanceof SongListItem) {
            return $item->guest_pass_id === $pass->id;
        }

        return $pass->can_add;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->route('item') instanceof SongListItem) {
            return [
                'type' => ['required', Rule::enum(SetlistSongType::class)],
                'note' => ['nullable', Rule::string()->max(1000)],
            ];
        }

        return [
            'canonical_track_id' => [
                'nullable',
                'integer',
                Rule::exists(CanonicalTrack::class, 'id')->where(fn ($query) => $query->whereIn('id', CanonicalTrack::query()->requestable()->select('id'))),
            ],
            'custom_artist' => ['required_without:canonical_track_id', 'nullable', 'prohibits:canonical_track_id', Rule::string()->max(255)],
            'custom_title' => ['nullable', Rule::string()->max(255)],
            'type' => ['required', Rule::enum(SetlistSongType::class)],
            'note' => ['nullable', Rule::string()->max(1000)],
            // Who's using a shared pass, e.g. "Aunt Sue" on a "Family" pass
            'guest_name' => ['nullable', Rule::string()->max(100)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'canonical_track_id' => __('dj.setlists.fields.song'),
            'custom_artist' => __('dj.setlists.fields.custom_artist'),
            'custom_title' => __('dj.setlists.fields.custom_title'),
            'type' => __('dj.setlists.fields.type'),
            'note' => __('dj.setlists.fields.note'),
            'guest_name' => __('dj.guest_passes.fields.guest_name'),
        ];
    }

    public function guestPass(): GuestPass
    {
        /** @var GuestPass $pass */
        $pass = $this->attributes->get('guest_pass') ?? request()->attributes->get('guest_pass');

        return $pass;
    }

    /**
     * @return array<string, mixed>
     */
    public function itemAttributes(): array
    {
        $name = trim((string) $this->validated('guest_name'));

        return [
            'canonical_track_id' => $this->validated('canonical_track_id'),
            'custom_artist' => $this->validated('custom_artist'),
            'custom_title' => $this->validated('custom_title'),
            'type' => $this->validated('type'),
            'note' => $this->validated('note'),
            'guest_pass_id' => $this->guestPass()->id,
            'guest_name' => $name !== '' ? $name : null,
        ];
    }
}
