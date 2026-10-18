<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Dj\Gig;
use App\Models\Dj\HistorySession;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * History sessions selected on a page, or suggested together, for linking to a gig, creating a
 * gig from them, unlinking, or changing whether stats count them
 */
class HistoryBulkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', HistorySession::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $action = $this->route()?->getName();

        return [
            'session_ids' => ['required', 'array', 'min:1', 'max:100'],
            'session_ids.*' => ['integer', 'distinct', Rule::exists(HistorySession::class, 'id')],
            'gig_id' => [Rule::when($action === 'admin.dj.history.bulk.link', ['required', 'integer', Rule::exists(Gig::class, 'id')], ['prohibited'])],
            'name' => [Rule::when($action === 'admin.dj.history.bulk.create-gig', ['nullable', Rule::string()->max(255)], ['prohibited'])],
            'include_in_stats' => [Rule::when($action === 'admin.dj.history.bulk.stats', ['required', 'boolean'], ['prohibited'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'session_ids' => __('dj.history.fields.sessions'),
            'gig_id' => __('dj.history.fields.gig'),
            'name' => __('dj.history.fields.gig_name'),
        ];
    }

    /**
     * @return Collection<int, HistorySession>
     */
    public function sessions(): Collection
    {
        return HistorySession::query()->whereKey($this->validated('session_ids'))->orderBy('started_at')->get();
    }
}
