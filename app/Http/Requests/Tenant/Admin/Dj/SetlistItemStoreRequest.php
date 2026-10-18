<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\SetlistSongType;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\SongList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A song for a setlist: one from the library, or a custom artist (and optional title)
 */
class SetlistItemStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $setlist = $this->route('setlist');

        return $setlist instanceof SongList && ($this->user()?->can('update', $setlist) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'canonical_track_id' => ['nullable', 'integer', Rule::exists(CanonicalTrack::class, 'id')],
            'custom_artist' => ['required_without:canonical_track_id', 'nullable', 'prohibits:canonical_track_id', Rule::string()->max(255)],
            'custom_title' => ['nullable', Rule::string()->max(255)],
            'type' => ['required', Rule::enum(SetlistSongType::class)],
            'note' => ['nullable', Rule::string()->max(1000)],
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemAttributes(): array
    {
        return [
            'canonical_track_id' => $this->validated('canonical_track_id'),
            'custom_artist' => $this->validated('custom_artist'),
            'custom_title' => $this->validated('custom_title'),
            'type' => $this->validated('type'),
            'note' => $this->validated('note'),
        ];
    }
}
