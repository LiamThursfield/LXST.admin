<?php

namespace App\Jobs\Cms;

use App\Enums\Cms\PublishStatus;
use App\Models\Cms\PageView;
use App\Models\Cms\Url;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Saves a page view sent by the site. Views of URLs that aren't published (or don't exist) are
 * dropped, so stats only count real pages.
 */
class RecordPageView implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $view  PageView attributes; the IP is already truncated and hashed
     */
    public function __construct(public array $view) {}

    public function handle(): void
    {
        $url = Url::query()
            ->withStatus(PublishStatus::Published)
            ->where('url_full', $this->view['url'] ?? null)
            ->first();

        if ($url === null) {
            return;
        }

        $pageView = new PageView($this->view);
        $pageView->viewable()->associate($url->urlable);
        $pageView->save();
    }
}
