<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\MusicalKey;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SongIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CanonicalTrack::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            // Matches the title or the artist's name
            'filter.search' => ['nullable', Rule::string()->max(255)],
            'filter.artist_id' => ['nullable', 'integer', Rule::exists(CanonicalArtist::class, 'id')],
            'filter.verified' => ['nullable', Rule::in(['yes', 'no'])],
            // In more than one library, e.g. a master.db and an XML library
            'filter.shared' => ['nullable', 'boolean'],
            // In no library's latest import
            'filter.orphaned' => ['nullable', 'boolean'],
            'filter.key' => ['nullable', Rule::enum(MusicalKey::class)],
            'filter.bpm_min' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'filter.bpm_max' => ['nullable', 'numeric', 'min:0', 'max:999', Rule::when($this->filled('filter.bpm_min'), 'gte:filter.bpm_min')],
            'sort' => ['nullable', Rule::in(['title', '-title', 'artist', '-artist', 'bpm', '-bpm', 'library_tracks_count', '-library_tracks_count'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }
}
