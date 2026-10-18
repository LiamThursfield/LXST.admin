<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Dj\Gig;

class GigUpdateRequest extends GigStoreRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $gig = $this->route('gig');

        return $gig instanceof Gig && ($this->user()?->can('update', $gig) ?? false);
    }
}
