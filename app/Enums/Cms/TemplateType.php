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
     * The field types a template of this type can have. Components and repeaters can't hold
     * fields that link to other content, and a repeater row can't hold another repeater.
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
                TemplateFieldType::Repeater,
            ],
            self::Repeater => [
                TemplateFieldType::Text,
                TemplateFieldType::Textarea,
                TemplateFieldType::Number,
                TemplateFieldType::Wysiwyg,
                TemplateFieldType::Image,
            ],
        };
    }

    public function allowsFieldType(TemplateFieldType $type): bool
    {
        return in_array($type, $this->allowedFieldTypes(), true);
    }
}
