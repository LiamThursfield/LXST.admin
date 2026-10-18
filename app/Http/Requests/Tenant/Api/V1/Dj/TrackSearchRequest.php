<?php

namespace App\Http\Requests\Tenant\Api\V1\Dj;

use App\Models\Dj\Gig;
use App\Services\Dj\CurrentGig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Searching the library to request a song, for a gig (the current one unless given)
 */
class TrackSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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
            'search' => ['required', Rule::string()->min(2)->max(100)],
            // The gig's public ID
            'gig' => ['nullable', 'string', Rule::exists(Gig::class, 'public_id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'search' => __('dj.api.fields.search'),
            'gig' => __('dj.api.fields.gig'),
        ];
    }

    public function searchTerm(): string
    {
        return trim((string) $this->validated('search'));
    }

    /**
     * The gig being requested for: the one given, or the current gig
     */
    public function gig(CurrentGig $currentGig): ?Gig
    {
        $publicId = $this->validated('gig');

        return $publicId === null
            ? $currentGig->resolve()
            : Gig::query()->where('public_id', $publicId)->first();
    }
}
