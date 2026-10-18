<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\CanonicalRecord;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Says a suggested pair of canonical records aren't the same
 */
class MatchDismissalStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->recordModel()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'record' => ['required', Rule::enum(CanonicalRecord::class)],
            'first_id' => ['required', 'integer', Rule::exists($this->recordModel(), 'id')],
            'second_id' => ['required', 'integer', 'different:first_id', Rule::exists($this->recordModel(), 'id')],
        ];
    }

    public function record(): CanonicalRecord
    {
        return CanonicalRecord::tryFrom((string) $this->input('record')) ?? CanonicalRecord::Track;
    }

    /**
     * @return class-string<CanonicalArtist|CanonicalTrack>
     */
    protected function recordModel(): string
    {
        return $this->record() === CanonicalRecord::Artist ? CanonicalArtist::class : CanonicalTrack::class;
    }
}
