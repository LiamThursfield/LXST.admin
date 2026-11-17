<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
use App\Http\Requests\Tenant\Admin\Cms\LayoutUpdateRequest;
use App\Mcp\Support\AdminRequest;
use App\Mcp\Support\ContentInput;
use App\Mcp\Support\Snapshots;
use App\Mcp\Support\ToolCall;
use App\Mcp\Tools\Cms\Concerns\FindsLayouts;
use App\Mcp\Tools\McpTool;
use App\Models\Cms\Layout;
use App\Models\User;
use App\Services\Cms\LayoutWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class UpdateLayout extends McpTool
{
    use FindsLayouts;

    protected string $name = 'update_layout';

    protected string $description = 'Changes a layout. Only what you send changes; content is merged field by field (send a field as null to clear it). Changes go live straight away on every page using the layout.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string(),
            'slug' => $schema->string(),
            'template_id' => $schema->integer()->description('Changing the template drops content for fields the new one doesn\'t have'),
            'content' => $schema->object()->description(CreateLayout::CONTENT_HELP),
        ];
    }

    public function ability(): ApiTokenAbility
    {
        return ApiTokenAbility::McpCmsStructure;
    }

    protected function module(): TenantModule
    {
        return TenantModule::Cms;
    }

    protected function userCan(User $user): bool
    {
        return $user->can('viewAny', Layout::class) && $user->can('create', Layout::class);
    }

    protected function changesContent(): bool
    {
        return true;
    }

    protected function run(Request $request, User $user, ToolCall $call): Response|ResponseFactory
    {
        $layout = $this->findLayout(new Request(['id' => $request->get('id')]));
        $call->target('layout', $layout);

        $before = Snapshots::layout($layout);
        $current = Snapshots::layoutInput($layout);
        $sent = $request->all();

        $input = [
            ...$current,
            ...Arr::only($sent, ['name', 'slug', 'template_id']),
            'content' => [...$current['content'], ...(array) ($sent['content'] ?? [])],
        ];
        $input['content'] = app(ContentInput::class)->forTemplate(TemplateType::Layout, $input['template_id'], $input['content']);

        app(LayoutWriter::class)->update($layout, AdminRequest::validate(LayoutUpdateRequest::class, $input, $user, ['layout' => $layout]));

        $after = Snapshots::layout($layout->fresh() ?? $layout);
        $call->changed($before, $after);

        return $this->result(['layout' => $after]);
    }
}
