<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Models\Cms\Template;
use Illuminate\Foundation\Http\FormRequest;

class ContentImportRequest extends FormRequest
{
    /** The most pasted JSON that's accepted, in characters */
    protected const int MAX_LENGTH = 2_000_000;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('create', Template::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'json' => ['required', 'string', 'max:'.self::MAX_LENGTH],
            'overwrite' => ['nullable', 'array'],
            'overwrite.*' => ['string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['json' => __('cms.import.json')];
    }

    /**
     * @return list<string>
     */
    public function overwriteKeys(): array
    {
        /** @var list<string> $keys */
        $keys = array_values($this->validated('overwrite') ?? []);

        return $keys;
    }
}
