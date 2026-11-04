<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Models\Bookings\BookableResource;
use Illuminate\Foundation\Http\FormRequest;

class RotaIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', BookableResource::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'week' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
