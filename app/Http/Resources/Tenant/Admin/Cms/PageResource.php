<?php

namespace App\Http\Resources\Tenant\Admin\Cms;

use App\Http\Resources\Traits\HasActions;
use App\Models\Cms\Metadata;
use App\Models\Cms\Page;
use App\Models\User;
use App\Support\UI\Action;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Page $page */
        $page = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('view', $page)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.cms.pages.show', $page))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $page)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.cms.pages.edit', $page))
                ->icon('i-lucide-pencil');
        }

        // Pages with children can't be deleted from the admin yet (forcing it is #90)
        if ($user?->can('delete', $page) && ! $this->hasChildren($page)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.cms.pages.destroy', $page))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('cms.pages.confirm_delete', ['name' => $page->name]));
        }

        return $actions;
    }

    protected function hasChildren(Page $page): bool
    {
        return isset($page->children_count) ? $page->children_count > 0 : $page->children()->exists();
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Page $page */
        $page = $this->resource;
        $url = $page->url;
        $status = $url?->status();

        return $this->appendActions($request, [
            'id' => $page->id,
            'template_id' => $page->template_id,
            'layout_id' => $page->layout_id,
            'parent_id' => $page->parent_id,
            'name' => $page->name,
            'slug' => $page->slug,
            'template' => $this->whenLoaded('template', fn () => ['id' => $page->template->id, 'name' => $page->template->name]),
            'url' => $url?->url_full,
            // How deep the page is, for indenting it under its parent: "/" and "/about" are 0, "/about/team" is 1
            'depth' => $url === null ? 0 : max(0, substr_count($url->url_full, '/') - 1),
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'is_enabled' => $url->is_enabled ?? false,
            'published_at' => $url?->published_at?->toIso8601String(),
            'expired_at' => $url?->expired_at?->toIso8601String(),
            // For datetime-local inputs, on the user's clock
            'published_at_local' => $this->localInput($url?->published_at, $request),
            'expired_at_local' => $this->localInput($url?->expired_at, $request),
            'children_count' => $this->whenCounted('children'),
            'content' => $this->whenLoaded('content', fn () => (object) $page->contentValues()),
            'metadata' => $this->whenLoaded('metadata', fn () => (object) collect(Metadata::FIELDS)
                ->mapWithKeys(fn (string $field) => [$field => $page->metadata?->{$field}])
                ->all()),
        ]);
    }

    protected function localInput(?CarbonInterface $date, Request $request): ?string
    {
        $user = $request->user();

        if ($date === null || ! $user instanceof User) {
            return null;
        }

        return $date->copy()->setTimezone($user->localTimezone())->format('Y-m-d\TH:i');
    }
}
