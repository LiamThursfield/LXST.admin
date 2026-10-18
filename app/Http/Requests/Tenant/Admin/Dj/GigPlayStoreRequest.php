<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A song added by hand to what a gig played, at a time on the gig's clock
 */
class GigPlayStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $gig = $this->route('gig');

        return $gig instanceof Gig && ($this->user()?->can('update', $gig) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'canonical_track_id' => ['required', 'integer', Rule::exists(CanonicalTrack::class, 'id')],
            'played_at' => ['required', 'date_format:Y-m-d\\TH:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'canonical_track_id' => __('dj.history.fields.song'),
            'played_at' => __('dj.history.fields.played_at'),
        ];
    }

    public function playedAt(Gig $gig): CarbonImmutable
    {
        return CarbonImmutable::parse((string) $this->validated('played_at'), $gig->timezone)->utc();
    }
}
