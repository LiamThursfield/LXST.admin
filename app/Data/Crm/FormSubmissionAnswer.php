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
    ) {}
}
