<?php

namespace App\Http\Requests\Tenant\Api\V1\Dj;

use App\Data\Dj\NowPlayingData;
use App\Models\Dj\Gig;
use App\Models\Dj\Library;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A track the CLI tool reports as played (see docs/api/dj-now-playing.md)
 */
class NowPlayingStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // The token and its ability are checked by `auth.tenant-token`
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Rekordbox's track ID, and the library it's in, when known
            'source_track_id' => ['nullable', 'string', 'max:255'],
            'library_id' => ['nullable', 'integer', Rule::exists(Library::class, 'id')],
            'artist' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            // Accepted for the CLI's records; matching uses the IDs, artist and title
            'bpm' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'key' => ['nullable', 'string', 'max:20'],
            'deck' => ['nullable', 'integer', 'min:1', 'max:8'],
            // When it was played (now if left out), at most a day ago and not in the future
            'played_at' => ['nullable', 'date', 'after:-1 day', 'before_or_equal:+1 minute'],
            // A gig's public ID, instead of the current gig
            'gig' => ['nullable', 'string', Rule::exists(Gig::class, 'public_id')],
        ];
    }

    public function report(): NowPlayingData
    {
        $playedAt = $this->validated('played_at');

        return new NowPlayingData(
            source_track_id: $this->validated('source_track_id'),
            library_id: $this->validated('library_id') === null ? null : (int) $this->validated('library_id'),
            artist: $this->filled('artist') ? trim((string) $this->validated('artist')) : null,
            title: trim((string) $this->validated('title')),
            played_at: $playedAt === null ? CarbonImmutable::now() : CarbonImmutable::parse((string) $playedAt)->utc(),
        );
    }

    public function gigPublicId(): ?string
    {
        $gig = $this->validated('gig');

        return $gig === null ? null : (string) $gig;
    }
}
