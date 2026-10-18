<?php

namespace App\Http\Requests\Tenant\Api\V1\Dj;

use App\Data\Dj\SongRequestData;
use App\Models\Dj\CanonicalTrack;
use App\Models\Dj\Gig;
use App\Models\Dj\GuestPass;
use App\Services\Cms\Api\VisitorPrivacy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A visitor requesting a song: one from library search, or a custom artist and (optional) title
 */
class SongRequestStoreRequest extends FormRequest
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
            // Only songs request search would find (#142)
            'canonical_track_id' => [
                'nullable',
                'integer',
                Rule::exists(CanonicalTrack::class, 'id')->where(fn ($query) => $query->whereIn('id', CanonicalTrack::query()->requestable()->select('id'))),
            ],
            'custom_artist' => ['required_without:canonical_track_id', 'nullable', 'prohibits:canonical_track_id', Rule::string()->max(255)],
            'custom_title' => ['nullable', Rule::string()->max(255)],
            'requester_name' => ['nullable', Rule::string()->max(100)],
            'message' => ['nullable', Rule::string()->max(500)],
            // The visitor's, which the site passes on when it calls from its server. Left out,
            // the request's own are used (right when the visitor's browser calls the API).
            'user_agent' => ['nullable', 'string', 'max:512'],
            'ip_address' => ['nullable', 'ip'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'canonical_track_id' => __('dj.requests.fields.song'),
            'custom_artist' => __('dj.requests.fields.custom_artist'),
            'custom_title' => __('dj.requests.fields.custom_title'),
            'requester_name' => __('dj.requests.fields.requester_name'),
            'message' => __('dj.requests.fields.message'),
        ];
    }

    public function visitorUserAgent(): ?string
    {
        return $this->filled('user_agent') ? $this->string('user_agent')->toString() : $this->userAgent();
    }

    public function visitorIp(): ?string
    {
        return $this->filled('ip_address') ? $this->string('ip_address')->toString() : $this->ip();
    }

    /**
     * The request, with the visitor hashed for the gig's day, so it holds for the whole gig
     */
    public function songRequest(Gig $gig, ?GuestPass $pass, VisitorPrivacy $privacy): SongRequestData
    {
        $optional = fn (string $key): ?string => trim((string) $this->validated($key)) !== '' ? trim((string) $this->validated($key)) : null;

        return new SongRequestData(
            canonical_track_id: $this->validated('canonical_track_id') === null ? null : (int) $this->validated('canonical_track_id'),
            custom_artist: $optional('custom_artist'),
            custom_title: $optional('custom_title'),
            requester_name: $optional('requester_name'),
            message: $optional('message'),
            visitor_hash: $privacy->visitorHash($this->visitorIp(), $this->visitorUserAgent(), $gig->starts_at),
            guest_pass: $pass,
        );
    }
}
