<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Dj\CanonicalArtist;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CanonicalArtistIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CanonicalArtist::class) ?? false;
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
            'filter.search' => ['nullable', Rule::string()->max(255)],
            'filter.verified' => ['nullable', Rule::in(['yes', 'no'])],
            // Linked to no library artist
            'filter.orphaned' => ['nullable', 'boolean'],
            // Hidden from song requests
            'filter.hidden' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['name', '-name', 'tracks_count', '-tracks_count', 'library_artists_count', '-library_artists_count'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }
}
