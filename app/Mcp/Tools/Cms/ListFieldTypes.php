<?php

namespace App\Mcp\Tools\Cms;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Enums\TenantModule;
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
class ListFieldTypes extends McpTool
{
    /**
     * What a page's content value looks like for each type of field
     */
    public const array CONTENT_SHAPES = [
        'text' => 'A string.',
        'textarea' => 'A string (line breaks kept).',
        'number' => 'A number.',
        'select' => 'One of the field\'s option values (settings.options[].value).',
        'wysiwyg' => 'Rich text: an HTML string, or {"html": "…"}. Only p, h1–h6, strong, em, u, s, ul, ol, li, blockquote, a, img, br and hr are kept. Existing values are {"json": TipTap document, "html": "…"}; send {"json": …} to keep one as is.',
        'image' => '{"file_id": File Manager file id, "alt": "…"}. Images must already be in the File Manager.',
        'link' => '{"link_type": "page", "page_id": 12} or {"link_type": "url", "url": "https://…"}, with an optional "target": "_blank".',
        'repeater' => 'A list of rows, each an object keyed by the repeater template\'s field slugs.',
        'component' => 'An object keyed by the component template\'s field slugs, or {"_saved_component_id": saved component id}.',
        'cms_menu' => 'A menu id (see list_menus).',
        'crm_form' => 'A CRM form id (see list_forms).',
        'crm_organisation_unit' => 'A CRM location (organisation unit) id.',
        'crm_opening_hours' => 'A CRM location (organisation unit) id whose opening hours to show.',
        'catalogue' => 'A catalogue id.',
    ];

    protected string $name = 'list_field_types';

    protected string $description = 'The types of field a template can have: the settings each takes, which template types allow it, whether this site can add it, and the shape of its value in page content.';

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
        /** @var Tenant $tenant */
        $tenant = tenant();

        return $this->result([
            'template_types' => collect(TemplateType::cases())->map(fn (TemplateType $type) => $type->value)->all(),
            'field_types' => collect(TemplateFieldType::cases())
                ->map(fn (TemplateFieldType $type) => [
                    'type' => $type->value,
                    'label' => $type->label(),
                    'settings' => [...$type->settingKeys(), 'width'],
                    'template_types' => collect(TemplateType::cases())
                        ->filter(fn (TemplateType $templateType) => $templateType->allowsFieldType($type))
                        ->map(fn (TemplateType $templateType) => $templateType->value)
                        ->values()
                        ->all(),
                    'available' => $type->isAvailable() && ($type->requiredModule() === null || $tenant->hasModule($type->requiredModule())),
                    'content' => self::CONTENT_SHAPES[$type->value],
                ])
                ->all(),
            'notes' => [
                'Field slugs key the content, so they are unique across a template (sections included) and can\'t start with "_".',
                'settings.width is "half" for a half-width field in the admin editor; leave it out for full width.',
                'Select fields need settings.options: [{"label": "…", "value": "…"}], and may have settings.default.',
                'Component and repeater fields need settings.template_id: a component or repeater template.',
            ],
        ]);
    }
}
