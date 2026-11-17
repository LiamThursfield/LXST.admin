<?php

namespace App\Enums\Api;

use App\Enums\Traits\HasLabels;

/**
 * What a tenant API token may do. Routes check one with the `auth.tenant-token:{ability}` middleware
 */
enum ApiTokenAbility: string
{
    use HasLabels;

    /** Syncing the DJ library, e.g. from the Rekordbox CLI tool */
    case DjSync = 'dj:sync';

    /** Pushing the DJ's now-playing tracks */
    case DjNowPlaying = 'dj:now-playing';

    /** MCP: reading site settings, layouts, templates, pages and menus */
    case McpCmsRead = 'mcp:cms:read';

    /** MCP: creating and editing draft pages */
    case McpCmsContent = 'mcp:cms:content';

    /** MCP: creating and editing templates and menus, which go live straight away */
    case McpCmsStructure = 'mcp:cms:structure';

    /** MCP: publishing and unpublishing pages, and editing pages that are live */
    case McpCmsPublish = 'mcp:cms:publish';

    /** MCP: reading, creating and editing CRM forms, which go live straight away */
    case McpCrmForms = 'mcp:crm:forms';

    /**
     * The abilities for the MCP server (`/api/mcp`)
     *
     * @return list<self>
     */
    public static function mcp(): array
    {
        return array_values(array_filter(self::cases(), fn (self $ability) => $ability->isMcp()));
    }

    public function isMcp(): bool
    {
        return str_starts_with($this->value, 'mcp:');
    }
}
