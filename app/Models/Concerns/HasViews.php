<?php

namespace App\Models\Concerns;

use App\Models\Cms\PageView;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For models whose visits are recorded (pages). Deleting the model keeps its views for site-wide
 * stats, just unlinked from it.
 *
 * @property-read Collection<int, PageView> $views
 */
trait HasViews
{
    public static function bootHasViews(): void
    {
        static::deleting(function (self $model) {
            $model->views()->update(['viewable_type' => null, 'viewable_id' => null]);
        });
    }

    /**
     * @return MorphMany<PageView, $this>
     */
    public function views(): MorphMany
    {
        return $this->morphMany(PageView::class, 'viewable');
    }
}
