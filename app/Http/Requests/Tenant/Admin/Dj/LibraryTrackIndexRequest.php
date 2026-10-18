<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\MusicalKey;
use App\Models\Dj\Library;
use App\Models\Dj\LibraryArtist;
use App\Models\Dj\LibraryTrack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LibraryTrackIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LibraryTrack::class) ?? false;
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
            'filter.library_id' => ['nullable', 'integer', Rule::exists(Library::class, 'id')],
            'filter.artist_id' => ['nullable', 'integer', Rule::exists(LibraryArtist::class, 'id')],
            'filter.genre' => ['nullable', Rule::string()->max(255)],
            'filter.key' => ['nullable', Rule::enum(MusicalKey::class)],
            // Also matches keys that mix harmonically with the chosen one
            'filter.compatible' => ['nullable', 'boolean'],
            'filter.bpm_min' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'filter.bpm_max' => ['nullable', 'numeric', 'min:0', 'max:999', Rule::when($this->filled('filter.bpm_min'), 'gte:filter.bpm_min')],
            // In minutes
            'filter.duration_min' => ['nullable', 'numeric', 'min:0', 'max:600'],
            'filter.duration_max' => ['nullable', 'numeric', 'min:0', 'max:600', Rule::when($this->filled('filter.duration_min'), 'gte:filter.duration_min')],
            // Tracks the latest import no longer had are hidden unless asked for
            'filter.missing' => ['nullable', 'boolean'],
            // Hidden from song requests
            'filter.hidden' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['title', '-title', 'artist', '-artist', 'bpm', '-bpm', 'duration', '-duration', 'key', '-key', 'date_added', '-date_added', 'play_count', '-play_count'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'filter.bpm_min' => __('dj.tracks.filters.bpm_min'),
            'filter.bpm_max' => __('dj.tracks.filters.bpm_max'),
            'filter.duration_min' => __('dj.tracks.filters.duration_min'),
            'filter.duration_max' => __('dj.tracks.filters.duration_max'),
        ];
    }
}
