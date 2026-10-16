<?php

namespace App\Http\Requests\Tenant\Admin\Cms;

use App\Models\Cms\Redirect;

class RedirectUpdateRequest extends RedirectStoreRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->currentRedirect()) ?? false;
    }

    protected function currentRedirect(): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->route('redirect');

        return $redirect;
    }
}
