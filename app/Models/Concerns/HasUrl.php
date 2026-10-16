<?php

namespace App\Models\Concerns;

use App\Models\Cms\Url;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * For models that live at a URL on the site and have a publishing window (pages)
 *
 * @property-read Url|null $url
 */
trait HasUrl
{
    /**
     * @return MorphOne<Url, $this>
     */
    public function url(): MorphOne
    {
        return $this->morphOne(Url::class, 'urlable');
    }
}
