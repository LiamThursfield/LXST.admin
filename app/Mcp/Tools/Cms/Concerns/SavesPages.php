<?php

namespace App\Mcp\Tools\Cms\Concerns;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Cms\TemplateType;
use App\Http\Requests\Tenant\Admin\Cms\PageUpdateRequest;
use App\Mcp\Support\AdminRequest;
use App\Mcp\Support\ContentInput;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Support\ToolRefused;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\User;
use App\Services\Cms\PageWriter;

/**
 * Saving a page through MCP. Pages made or edited without `mcp:cms:publish` stay drafts: their
 * publishing fields can't be set, a live page can't be edited, and a page can't be moved or renamed
 * while live pages sit under it (their URLs would change).
 */
trait SavesPages
{
    /**
     * Rich text sent as HTML becomes the editor's JSON, for the template the page will use
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function contentInput(mixed $templateId, array $content): array
    {
        $template = is_numeric($templateId)
            ? Template::query()->where('type', TemplateType::Page)->whereKey((int) $templateId)->first()
            : null;

        return $template === null ? $content : app(ContentInput::class)->normalise($template, $content);
    }

    protected function refuseUnlessDraft(Page $page): void
    {
        if (($page->url->is_enabled ?? false) && ! $this->tokenHas(ApiTokenAbility::McpCmsPublish)) {
            throw new ToolRefused(__('mcp.errors.live_page'));
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function refuseMovingLivePages(Page $page, array $input): void
    {
        $moves = $input['slug'] !== $page->slug || $input['parent_id'] !== $page->parent_id;

        if (! $moves || $this->tokenHas(ApiTokenAbility::McpCmsPublish)) {
            return;
        }

        $liveBelow = Page::query()
            ->whereKey($page->descendants()->pluck('id'))
            ->whereHas('url', fn ($query) => $query->where('is_enabled', true))
            ->exists();

        if ($liveBelow) {
            throw new ToolRefused(__('mcp.errors.live_descendants'));
        }
    }

    /**
     * Saves the page as the admin's page form would, recording what changed
     *
     * @param  array<string, mixed>  $input  The whole page, as Snapshots::pageInput() gives it
     * @return array<string, mixed>
     */
    protected function savePage(Page $page, array $input, User $user, ToolCall $call): array
    {
        $before = Snapshots::page($page, $user);

        app(PageWriter::class)->update($page, AdminRequest::validate(PageUpdateRequest::class, $input, $user, ['page' => $page]));

        $page = $page->fresh() ?? $page;
        $after = Snapshots::page($page, $user);
        $call->changed($before, $after);

        return $after;
    }
}
