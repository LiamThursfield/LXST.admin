<?php

namespace App\Http\Controllers\Tenant\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Api\V1\Cms\PageShowRequest;
use App\Models\Cms\Metadata;
use App\Models\Cms\Page;
use App\Models\Cms\Redirect;
use App\Services\Cms\Api\ApiResponseCache;
use App\Services\Cms\Api\ContentPresenter;
use App\Services\Cms\Api\PageResolver;
use Illuminate\Http\JsonResponse;

/**
 * The main endpoint for headless sites: send the URL being visited, get back the page to show,
 * or a redirect to follow
 */
class PageController extends Controller
{
    public function __construct(
        protected PageResolver $resolver,
        protected ContentPresenter $presenter,
        protected ApiResponseCache $cache,
    ) {}

    public function show(PageShowRequest $request): JsonResponse
    {
        $path = $request->sitePath();
        $format = $request->richTextFormat();

        $cached = $this->cache->remember("page|{$path}|{$format}", fn () => $this->resolve($path, $format));

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
