<?php

namespace App\Http\Requests\Tenant\Admin\Bookings;

use App\Http\Requests\Tenant\Admin\Bookings\Concerns\ValidatesServiceSession;
use App\Models\Bookings\ServiceSession;
use Illuminate\Foundation\Http\FormRequest;

class ServiceSessionStoreRequest extends FormRequest
{
    use ValidatesServiceSession;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ServiceSession::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sessionRules();
    }
}
