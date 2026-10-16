<?php

namespace App\Models\Concerns;

use App\Models\Cms\Metadata;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * For models with SEO and social sharing details (pages)
 *
 * @property-read Metadata|null $metadata
 */
trait HasMetadata
{
    /**
     * @return MorphOne<Metadata, $this>
     */
    public function metadata(): MorphOne
    {
        return $this->morphOne(Metadata::class, 'metadatable');
    }
}
