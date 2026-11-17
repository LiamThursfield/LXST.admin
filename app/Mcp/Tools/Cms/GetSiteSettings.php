<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\TenantModule;
use App\Http\Controllers\Tenant\Api\V1\SiteController;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\McpTool;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorisation\Enums\CmsPermission;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetSiteSettings extends McpTool
{
    protected string $name = 'get_site_settings';

    protected string $description = 'The site\'s settings as its website receives them from GET /api/v1/site (name, industry, logo, theme colours, Google Tag Manager ID and menus by slug), plus the site\'s domain and which modules it has.';

    public function ability(): ApiTokenAbility
    {
        return ApiTokenAbility::McpCmsRead;
    }

    protected function module(): TenantModule
    {
        return TenantModule::Cms;
    }

    protected function userCan(User $user): bool
    {
        return $user->can(CmsPermission::ViewContent) || $user->can(CmsPermission::ViewStructure);
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        /** @var array{data: array<string, mixed>} $site */
        $site = json_decode((string) app(SiteController::class)()->getContent(), true);
        /** @var Tenant $tenant */
        $tenant = tenant();

        return $this->result([
            'domain' => request()->getHost(),
            'modules' => collect(TenantModule::cases())
                ->filter(fn (TenantModule $module) => $tenant->hasModule($module))
                ->map(fn (TenantModule $module) => $module->value)
                ->values()
                ->all(),
            'site' => $site['data'],
        ]);
    }
}
