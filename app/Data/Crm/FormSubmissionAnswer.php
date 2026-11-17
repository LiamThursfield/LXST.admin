<?php

namespace App\Data\Crm;

use App\Enums\Crm\FormFieldType;
use Spatie\LaravelData\Data;

/**
 * One answer in a form submission, with the field's details as they were when it was submitted,
 * so the submission still reads correctly after the form's fields are renamed or removed
 */
class FormSubmissionAnswer extends Data
{
    public function __construct(
        public string $slug,
        public string $label,
        public FormFieldType $type,
        public string|int|float|null $value,
        /** The chosen option's label when it was submitted, as the value is a key the site sends (select) */
        public ?string $option_label = null,
    ) {}

    /**
     * The answer as people should read it: the chosen option's label, otherwise the value
     */
    public function displayValue(): string|int|float|null
    {
        return $this->option_label ?? $this->value;
    }
}
