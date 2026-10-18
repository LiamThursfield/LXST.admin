<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\MusicalKey;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corrects a song's details. Editing verifies it, so matching never changes it back.
 */
class SongUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $song = $this->route('song');

        return $song instanceof CanonicalTrack && ($this->user()?->can('update', $song) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', Rule::string()->max(255)],
            'canonical_artist_id' => ['nullable', 'integer', Rule::exists(CanonicalArtist::class, 'id')],
            'bpm' => ['nullable', 'numeric', 'min:1', 'max:999'],
            'key' => ['nullable', Rule::enum(MusicalKey::class)],
            // Minutes and seconds, e.g. 3:45
            'duration' => ['nullable', 'regex:/^\d{1,3}:[0-5]\d$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('dj.songs.fields.title'),
            'canonical_artist_id' => __('dj.songs.fields.artist'),
            'bpm' => __('dj.songs.fields.bpm'),
            'key' => __('dj.songs.fields.key'),
            'duration' => __('dj.songs.fields.duration'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'duration.regex' => __('dj.songs.errors.duration'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function songAttributes(): array
    {
        /** @var string|null $duration */
        $duration = $this->validated('duration');
        $durationSeconds = null;

        if ($duration !== null) {
            [$minutes, $seconds] = array_map('intval', explode(':', $duration));
            $durationSeconds = $minutes * 60 + $seconds;
        }

        return [
            'title' => $this->validated('title'),
            'canonical_artist_id' => $this->validated('canonical_artist_id'),
            'bpm' => $this->validated('bpm'),
            'key' => $this->validated('key'),
            'duration' => $durationSeconds,
        ];
    }
}
