<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\LibraryArtist;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Library artists selected on a page, for a bulk action: assigning them to a canonical artist (an
 * existing one, or a new one), or verifying their canonical artists
 */
class LibraryArtistBulkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', CanonicalArtist::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assigning = $this->routeIs('admin.dj.artists.bulk.assign');

        return [
            // The most a page shows
            'artist_ids' => ['required', 'array', 'min:1', 'max:100'],
            'artist_ids.*' => ['integer', 'distinct', Rule::exists(LibraryArtist::class, 'id')],
            'canonical_artist_id' => [Rule::when($assigning, ['nullable', 'integer', Rule::exists(CanonicalArtist::class, 'id')], ['prohibited'])],
            // A new canonical artist, used when none is chosen
            'new_artist_name' => [Rule::when($assigning, ['required_without:canonical_artist_id', 'nullable', Rule::string()->max(255)], ['prohibited'])],
            'verified' => [Rule::when($assigning, ['prohibited'], ['required', 'boolean'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'artist_ids' => __('dj.bulk.fields.artists'),
            'canonical_artist_id' => __('dj.bulk.fields.artist'),
            'new_artist_name' => __('dj.bulk.fields.new_artist_name'),
        ];
    }

    /**
     * @return Collection<int, LibraryArtist>
     */
    public function artists(): Collection
    {
        return LibraryArtist::query()->whereKey($this->validated('artist_ids'))->orderBy('id')->get();
    }
}
