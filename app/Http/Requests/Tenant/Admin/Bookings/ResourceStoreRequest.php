<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Http\Requests\Tenant\Admin\Bookings\Concerns\ValidatesResource;
use App\Models\Bookings\BookableResource;
use Illuminate\Foundation\Http\FormRequest;

class ResourceStoreRequest extends FormRequest
{
    use ValidatesResource;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', BookableResource::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->resourceRules();
    }
}
