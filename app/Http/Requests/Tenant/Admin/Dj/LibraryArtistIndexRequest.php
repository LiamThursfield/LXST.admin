<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Dj\Library;
use App\Models\Dj\LibraryArtist;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LibraryArtistIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LibraryArtist::class) ?? false;
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
            'filter.library_id' => ['nullable', 'integer', Rule::exists(Library::class, 'id')],
            // Hidden from song requests
            'filter.hidden' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['name', '-name', 'tracks_count', '-tracks_count'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ];
    }
}
