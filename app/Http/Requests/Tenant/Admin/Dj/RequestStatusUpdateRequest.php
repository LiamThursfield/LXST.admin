<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\RequestStatus;
use App\Models\Dj\SongListItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The DJ accepting, declining or marking a request played, or putting it back to pending (undo)
 */
class RequestStatusUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof SongListItem && ($this->user()?->can('updateStatus', $item) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(RequestStatus::class)],
        ];
    }

    public function status(): RequestStatus
    {
        return RequestStatus::from((string) $this->validated('status'));
    }
}
