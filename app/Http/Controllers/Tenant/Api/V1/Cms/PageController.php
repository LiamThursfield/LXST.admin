<?php

namespace App\Http\Controllers\Tenant\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Cms\PageShowRequest;
use App\Models\Cms\Metadata;
use App\Models\Cms\Page;
use App\Models\Cms\Redirect;
use App\Services\Cms\Api\ContentPresenter;
use App\Services\Cms\Api\ContentVersion;
use App\Services\Cms\Api\PageResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The main endpoint for headless sites: send the URL being visited, get back the page to show,
 * or a redirect to follow
 */
class PageController extends Controller
{
    /**
     * How long a response is cached at most. Any admin change refreshes it sooner (ContentVersion),
     * and so does the next scheduled publish or expiry.
     */
    protected const int CACHE_SECONDS = 3600;

    public function __construct(
        protected PageResolver $resolver,
        protected ContentPresenter $presenter,
        protected ContentVersion $version,
    ) {}

    public function show(PageShowRequest $request): JsonResponse
    {
        $path = $request->sitePath();
        $format = $request->richTextFormat();
        // The key includes the next scheduled publish or expiry, so once it passes the key changes
        // and the page is resolved again, whatever the cache store's own expiry does
        $nextChange = $this->resolver->nextScheduledChange();
        $key = 'cms-api:page:'.$this->version->current().':'.($nextChange?->getTimestamp() ?? 'none').':'.sha1("{$path}|{$format}");
        $seconds = $nextChange === null
            ? self::CACHE_SECONDS
            : (int) max(1, min(self::CACHE_SECONDS, now()->diffInSeconds($nextChange)));

        /** @var array{status: int, body: array<string, mixed>} $cached */
        $cached = Cache::remember($key, $seconds, fn () => $this->resolve($path, $format));

        return response()->json($cached['body'], $cached['status']);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    protected function resolve(string $path, string $format): array
    {
        $redirect = $this->resolver->redirect($path);

        if ($redirect !== null) {
            return ['status' => 200, 'body' => ['data' => $this->redirect($redirect)]];
        }

        $page = $this->resolver->page($path);

        if ($page === null) {
            return ['status' => 404, 'body' => ['message' => __('cms.api.not_found', ['url' => $path])]];
        }

        return ['status' => 200, 'body' => ['data' => $this->page($page, $format)]];
    }

    /**
     * @return array<string, mixed>
     */
    protected function redirect(Redirect $redirect): array
    {
        return [
            'type' => 'redirect',
            'redirect' => [
                'from' => $redirect->url_from,
                'to' => $redirect->url_to,
                'status' => $redirect->httpStatus(),
                'permanent' => $redirect->is_permanent,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function page(Page $page, string $format): array
    {
        $layout = $page->layout;
        $metadata = $page->metadata;

        return [
            'type' => 'page',
            'page' => [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'url' => $page->url?->url_full,
                'template' => $page->template->slug,
                'published_at' => $page->url?->published_at?->toIso8601String(),
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
            'metadata' => [
                ...collect(Metadata::FIELDS)
                    ->reject(fn (string $field) => $field === 'og_image_file_id')
                    ->mapWithKeys(fn (string $field) => [$field => $metadata?->{$field}])
                    ->all(),
                'og_image' => $metadata?->og_image_file_id === null
                    ? null
                    : $this->presenter->image(['file_id' => $metadata->og_image_file_id]),
            ],
            'layout' => [
                'slug' => $layout->slug,
                'template' => $layout->template->slug,
                'content' => (object) $this->presenter->present($layout->template, $layout->contentValues(), $format),
            ],
            'content' => (object) $this->presenter->present($page->template, $page->contentValues(), $format),
        ];
    }
}
