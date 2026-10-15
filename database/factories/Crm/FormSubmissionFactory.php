<?php

namespace Database\Factories\Crm;

use App\Data\Crm\FormSubmissionAnswer;
use App\Data\Crm\FormSubmissionData;
use App\Enums\Crm\FormFieldType;
use App\Models\Crm\Contact;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Models\Crm\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    protected $model = FormSubmission::class;

    /**
     * Define the model's default state (a genuine submission with a single message answer).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'contact_id' => Contact::factory(),
            'is_spam' => false,
            'data' => new FormSubmissionData(
                answers: [new FormSubmissionAnswer('message', 'Message', FormFieldType::Textarea, fake()->sentence())],
            ),
            'submitted_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }

    /**
     * Answers for each of the given form's fields, taking contact fields from the contact
     */
    public function answering(Form $form, ?Contact $contact = null): static
    {
        return $this->state(function (array $attributes) use ($form, $contact) {
            $answers = $form->fields->map(function (FormField $field) use ($contact) {
                $contactAttribute = $field->type->contactAttribute();

                $value = $contactAttribute !== null
                    ? $contact?->getAttribute($contactAttribute)
                    : match ($field->type) {
                        FormFieldType::Number => fake()->numberBetween(1, 10),
                        FormFieldType::Email => fake()->safeEmail(),
                        default => fake()->sentence(),
                    };

                return new FormSubmissionAnswer($field->slug, $field->name, $field->type, $value);
            })->all();

            $marketing = collect(['marketing_email', 'marketing_sms', 'marketing_telephone'])
                ->filter(fn (string $key) => $form->getAttribute($key))
                ->mapWithKeys(fn (string $key) => [$key => fake()->boolean()])
                ->all();

            return [
                'form_id' => $form->id,
                'contact_id' => $contact?->id,
                'data' => new FormSubmissionData($answers, $marketing),
            ];
        });
    }

    public function spam(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_spam' => true,
        ]);
    }
}
