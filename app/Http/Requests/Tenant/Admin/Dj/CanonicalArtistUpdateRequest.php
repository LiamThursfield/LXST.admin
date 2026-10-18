<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Dj\CanonicalArtist;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Renames a canonical artist. Editing verifies it, so matching never changes it back.
 */
class CanonicalArtistUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $artist = $this->route('canonicalArtist');

        return $artist instanceof CanonicalArtist && ($this->user()?->can('update', $artist) ?? false);
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('dj.canonical_artists.fields.name'),
        ];
    }
}
