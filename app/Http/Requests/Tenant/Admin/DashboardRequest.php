<?php

namespace App\Http\Requests\Tenant\Admin;

use App\Enums\Cms\AnalyticsRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The page view stats' date range
            'range' => ['nullable', Rule::enum(AnalyticsRange::class)],
        ];
    }

    public function analyticsRange(): AnalyticsRange
    {
        return AnalyticsRange::tryFrom($this->string('range')->toString()) ?? AnalyticsRange::Days30;
    }
}
