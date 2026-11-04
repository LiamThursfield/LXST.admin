<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Http\Requests\Tenant\Admin\Bookings\Concerns\ValidatesService;
use App\Models\Bookings\Service;
use Illuminate\Foundation\Http\FormRequest;

class ServiceStoreRequest extends FormRequest
{
    use ValidatesService;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Service::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->serviceRules();
    }
}
