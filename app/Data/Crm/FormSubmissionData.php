<?php

namespace App\Data\Crm;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * The contents of a form submission
 */
class FormSubmissionData extends Data
{
    /**
     * @param  list<FormSubmissionAnswer>  $answers  The answers, in the form's field order
     * @param  array<string, bool>  $marketing  The answers to the marketing consent questions the form asked,
     *                                          keyed by marketing_email, marketing_sms and marketing_telephone
     */
    public function __construct(
        #[DataCollectionOf(FormSubmissionAnswer::class)]
        public array $answers = [],
        public array $marketing = [],
    ) {}
}
