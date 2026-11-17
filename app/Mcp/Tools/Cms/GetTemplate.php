<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\Cms\Concerns\FindsTemplates;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Template;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTemplate extends McpTool
{
    use FindsTemplates;

    protected string $name = 'get_template';

    protected string $description = 'A template with its fields and sections, in the shape update_template takes. Fields in no section are under "fields"; the rest are under their section. Page content is keyed by these field slugs.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The template\'s id'),
            'slug' => $schema->string()->description('Or its slug, with its type'),
            'type' => $schema->string()->enum(array_map(fn (TemplateType $type) => $type->value, TemplateType::cases()))
                ->description('The template\'s type, when finding it by slug (slugs are unique per type). Defaults to page.'),
        ];
    }

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
        return $user->can('viewAny', Template::class);
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $template = $this->findTemplate($request);
        Gate::forUser($user)->authorize('view', $template);
        $call->target('template', $template);

        return $this->result(['template' => Snapshots::template($template)]);
    }
}
