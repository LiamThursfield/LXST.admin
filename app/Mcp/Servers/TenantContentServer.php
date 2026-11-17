<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Cms\CreateLayout;
use App\Mcp\Tools\Cms\CreateMenu;
use App\Mcp\Tools\Cms\CreatePage;
use App\Mcp\Tools\Cms\CreateTemplate;
use App\Mcp\Tools\Cms\GetLayout;
use App\Mcp\Tools\Cms\GetMenu;
use App\Mcp\Tools\Cms\GetPage;
use App\Mcp\Tools\Cms\GetSiteSettings;
use App\Mcp\Tools\Cms\GetTemplate;
use App\Mcp\Tools\Cms\ListFieldTypes;
use App\Mcp\Tools\Cms\ListLayouts;
use App\Mcp\Tools\Cms\ListMenus;
use App\Mcp\Tools\Cms\ListPages;
use App\Mcp\Tools\Cms\ListTemplates;
use App\Mcp\Tools\Cms\PublishPage;
use App\Mcp\Tools\Cms\UnpublishPage;
use App\Mcp\Tools\Cms\UpdateLayout;
use App\Mcp\Tools\Cms\UpdateMenu;
use App\Mcp\Tools\Cms\UpdatePage;
use App\Mcp\Tools\Cms\UpdateTemplate;
use App\Mcp\Tools\Crm\CreateForm;
use App\Mcp\Tools\Crm\GetForm;
use App\Mcp\Tools\Crm\ListForms;
use App\Mcp\Tools\Crm\UpdateForm;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The tenant's MCP server (`POST /api/mcp` on the tenant's domain), for an AI assistant to manage
 * the site's content with a tenant API token. Each tool is only listed when the token has its
 * `mcp:*` ability, the site has its module and the token's user has the admin permissions it needs
 * (see McpTool). See docs/api/mcp.md.
 */
#[Name('LXST.admin site content')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Manages one website's content in LXST.admin, a headless CMS: its templates, pages, menus and CRM forms. Websites read it through the site's public API.

    - Templates define fields. Pages use a page template, and their content is keyed by its field slugs. Read a template with get_template, and list_field_types for the shape of each field's value.
    - Every page has a layout (list_layouts; create_layout makes one from a layout template) and a URL: its parent's URL plus its slug. A top-level page with no slug is the home page.
    - Pages are created as drafts. Publishing is for a person in the admin, unless this token can use publish_page. Live pages can only be edited with that ability too.
    - Layouts, templates, menus and forms have no drafts: changes to them go live straight away, so check before changing ones in use.
    - Nothing can be deleted through this server, and fields can't be removed from templates or forms.
    - Updates only change what you send. Validation errors list each field's problem; fix them and try again.
    - Every call is logged for the site's owners to review.
    MARKDOWN)]
class TenantContentServer extends Server
{
    /** Lists every tool on one page, so clients that don't follow cursors still see them all */
    public int $defaultPaginationLength = 50;

    /**
     * Tools the token, site or user can't use are left out of the list (see McpTool::shouldRegister())
     *
     * @var array<int, class-string<Server\Tool>>
     */
    protected array $tools = [
        GetSiteSettings::class,
        ListLayouts::class,
        GetLayout::class,
        CreateLayout::class,
        UpdateLayout::class,
        ListFieldTypes::class,
        ListTemplates::class,
        GetTemplate::class,
        CreateTemplate::class,
        UpdateTemplate::class,
        ListPages::class,
        GetPage::class,
        CreatePage::class,
        UpdatePage::class,
        PublishPage::class,
        UnpublishPage::class,
        ListMenus::class,
        GetMenu::class,
        CreateMenu::class,
        UpdateMenu::class,
        ListForms::class,
        GetForm::class,
        CreateForm::class,
        UpdateForm::class,
    ];
}
