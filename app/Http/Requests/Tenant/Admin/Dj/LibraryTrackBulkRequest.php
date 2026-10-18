<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\LibraryTrack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Library tracks selected on a page, for a bulk action: assigning them to a song (an existing one,
 * or a new one), verifying their songs, or hiding their songs from requests
 */
class LibraryTrackBulkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', CanonicalTrack::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assigning = $this->routeIs('admin.dj.tracks.bulk.assign');

        return [
            // The most a page shows
            'track_ids' => ['required', 'array', 'min:1', 'max:100'],
            'track_ids.*' => ['integer', 'distinct', Rule::exists(LibraryTrack::class, 'id')],
            'song_id' => [Rule::when($assigning, ['nullable', 'integer', Rule::exists(CanonicalTrack::class, 'id')], ['prohibited'])],
            // A new song, used when no song is chosen
            'new_song_title' => [Rule::when($assigning, ['required_without:song_id', 'nullable', Rule::string()->max(255)], ['prohibited'])],
            'verified' => [Rule::when($this->routeIs('admin.dj.tracks.bulk.verify'), ['required', 'boolean'], ['prohibited'])],
            'hidden' => [Rule::when($this->routeIs('admin.dj.tracks.bulk.hide'), ['required', 'boolean'], ['prohibited'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'track_ids' => __('dj.bulk.fields.tracks'),
            'song_id' => __('dj.bulk.fields.song'),
            'new_song_title' => __('dj.bulk.fields.new_song_title'),
        ];
    }

    /**
     * @return Collection<int, LibraryTrack>
     */
    public function tracks(): Collection
    {
        return LibraryTrack::query()->with('artist')->whereKey($this->validated('track_ids'))->orderBy('id')->get();
    }
}
