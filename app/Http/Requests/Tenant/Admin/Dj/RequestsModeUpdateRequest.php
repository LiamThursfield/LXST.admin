<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\RequestsMode;
use App\Models\Dj\SongListItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Opening or closing a gig's requests from the queue, or back to following DJ settings (null)
 */
class RequestsModeUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewRequests', SongListItem::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'requests_mode' => ['nullable', Rule::enum(RequestsMode::class)->only([RequestsMode::Open, RequestsMode::Closed])],
        ];
    }

    public function mode(): ?RequestsMode
    {
        $mode = $this->validated('requests_mode');

        return $mode === null ? null : RequestsMode::from((string) $mode);
    }
}
