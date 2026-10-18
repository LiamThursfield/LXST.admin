<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Enums\Dj\SongListVisibility;
use App\Models\Dj\Gig;
use App\Models\Dj\SongList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetlistStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', SongList::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', Rule::string()->max(255)],
            'gig_id' => ['nullable', 'integer', Rule::exists(Gig::class, 'id')],
            'visibility' => ['required', Rule::enum(SongListVisibility::class)],
            'description' => ['nullable', Rule::string()->max(5000)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('dj.setlists.fields.name'),
            'gig_id' => __('dj.setlists.fields.gig'),
            'visibility' => __('dj.setlists.fields.visibility'),
            'description' => __('dj.setlists.fields.description'),
        ];
    }
}
