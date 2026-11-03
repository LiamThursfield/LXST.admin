<?php

namespace App\Enums\Cms;

use App\Enums\Traits\HasLabels;

/**
 * What a template defines the fields for. Components and repeaters are reusable groups of
 * fields placed inside other templates (one set of values, or a list of rows).
 */
enum TemplateType: string
{
    use HasLabels;

    case Page = 'page';
    case Layout = 'layout';
    case Component = 'component';
    case Repeater = 'repeater';

    /**
     * The field types a template of this type can have. Components and repeaters can hold other
     * components, but not fields that link to other content such as menus or forms. A repeater
     * row can't directly hold another repeater, though a component in the row can, up to
     * Template::MAX_REPEATER_LEVELS.
     *
     * @return list<TemplateFieldType>
     */
    public function allowedFieldTypes(): array
    {
        return match ($this) {
            self::Page, self::Layout => TemplateFieldType::cases(),
            self::Component => [
                TemplateFieldType::Text,
                TemplateFieldType::Textarea,
                TemplateFieldType::Number,
                TemplateFieldType::Wysiwyg,
                TemplateFieldType::Image,
                TemplateFieldType::Component,
                TemplateFieldType::Repeater,
            ],
            self::Repeater => [
                TemplateFieldType::Text,
                TemplateFieldType::Textarea,
                TemplateFieldType::Number,
                TemplateFieldType::Wysiwyg,
                TemplateFieldType::Image,
                TemplateFieldType::Component,
            ],
        };
    }

    public function allowsFieldType(TemplateFieldType $type): bool
    {
        return in_array($type, $this->allowedFieldTypes(), true);
    }
}
