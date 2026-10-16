<?php

namespace App\Enums\Cms;

use App\Enums\TenantModule;
use App\Enums\Traits\HasLabels;

/**
 * The types of field a CMS template can have. Each needs a content editor (#48–#53), so a type is
 * only offered in the template builder once its editor exists (see isAvailable()).
 */
enum TemplateFieldType: string
{
    use HasLabels;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Wysiwyg = 'wysiwyg';
    case Image = 'image';
    case Repeater = 'repeater';
    case Component = 'component';
    case CmsMenu = 'cms_menu';
    case CrmForm = 'crm_form';
    case CrmOrganisationUnit = 'crm_organisation_unit';

    /**
     * Whether the type's content editor exists, so it can be offered in the template builder.
     * Every type has one now; a new type can return false here until its editor is built.
     */
    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * The tenant module the type needs, if any
     */
    public function requiredModule(): ?TenantModule
    {
        return match ($this) {
            self::CrmForm, self::CrmOrganisationUnit => TenantModule::Crm,
            default => null,
        };
    }

    /**
     * The type of template a component or repeater field uses for its own fields
     */
    public function nestedTemplateType(): ?TemplateType
    {
        return match ($this) {
            self::Component => TemplateType::Component,
            self::Repeater => TemplateType::Repeater,
            default => null,
        };
    }

    /**
     * The TemplateFieldSettings that apply to this type
     *
     * @return list<string>
     */
    public function settingKeys(): array
    {
        return match ($this) {
            self::Text => ['max_length'],
            self::Textarea => ['max_length', 'rows'],
            self::Number => ['min', 'max'],
            self::Component => ['template_id'],
            self::Repeater => ['template_id', 'min_items', 'max_items'],
            self::CrmOrganisationUnit => ['organisation_unit_type'],
            default => [],
        };
    }
}
