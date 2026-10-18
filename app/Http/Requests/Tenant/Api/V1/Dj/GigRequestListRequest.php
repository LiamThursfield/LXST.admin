<?php

namespace App\Http\Requests\Tenant\Api\V1\Dj;

use App\Http\Requests\Concerns\IdentifiesVisitor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A visitor viewing a gig's public request list, or upvoting a request on it
 */
class GigRequestListRequest extends FormRequest
{
    use IdentifiesVisitor;

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
        return $this->visitorRules();
    }
}
