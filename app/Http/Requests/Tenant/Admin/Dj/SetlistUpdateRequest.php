<?php

namespace App\Http\Requests\Tenant\Admin\Dj;

use App\Models\Dj\SongList;

class SetlistUpdateRequest extends SetlistStoreRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $setlist = $this->route('setlist');

        return $setlist instanceof SongList && ($this->user()?->can('update', $setlist) ?? false);
    }
}
