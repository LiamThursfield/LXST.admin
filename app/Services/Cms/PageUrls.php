<?php

namespace App\Services\Cms;

use App\Models\Cms\Page;
use App\Models\Cms\Url;

/**
 * Keeps pages' URLs in step with their slugs and parents
 */
class PageUrls
{
    /**
     * Saves the page's URL and publishing window, then rebuilds every descendant's URL, as
     * changing a slug or moving a page changes theirs too
     *
     * @param  array{is_enabled: bool, published_at: mixed, expired_at: mixed}  $publishing
     */
    public function sync(Page $page, array $publishing): void
    {
        $page->load('parent.url');
        $old = $page->url?->url_full;

        $url = $page->url()->updateOrCreate([], [
            'url_main' => $page->urlMain(),
            'url_full' => $page->urlFull(),
            ...$publishing,
        ]);

        $page->setRelation('url', $url);

        if ($old !== null && $old !== $url->url_full) {
            $this->rebuildChildren($page, $url->url_full);
        }
    }

    /**
     * @param  string  $parentUrl  The parent's new full URL
     */
    protected function rebuildChildren(Page $parent, string $parentUrl): void
    {
        foreach ($parent->children()->with('url')->get() as $child) {
            $urlFull = $child->urlFull($parentUrl);

            if ($child->url instanceof Url) {
                $child->url->update(['url_full' => $urlFull]);
            }

            $this->rebuildChildren($child, $urlFull);
        }
    }
}
