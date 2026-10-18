<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\CanonicalRecord;
use App\Models\Dj\CanonicalArtist;
use App\Models\Dj\CanonicalTrack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Merges one canonical record (the source) into another (the target, which is kept)
 */
class CanonicalMergeStoreRequest extends FormRequest
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
            'target_id' => ['required', 'integer', Rule::exists($this->recordModel(), 'id')],
            'source_id' => ['required', 'integer', 'different:target_id', Rule::exists($this->recordModel(), 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_id.different' => __('dj.matching.errors.same_record'),
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
