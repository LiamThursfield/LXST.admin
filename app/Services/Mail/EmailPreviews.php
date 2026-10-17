<?php

namespace App\Services\Mail;

use App\Data\Crm\FormSubmissionAnswer;
use App\Data\Crm\FormSubmissionData;
use App\Enums\Crm\FormFieldType;
use App\Enums\TenantModule;
use App\Mail\Crm\FormSubmittedMail;
use App\Mail\Settings\MailSettingsTestMail;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Models\Crm\FormSubmission;
use App\Models\Settings\GeneralSettings;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Collection;

/**
 * The emails the admin can preview (Site Settings › Email previews), each rendered with sample
 * data. To make a new email previewable, add it to all().
 */
class EmailPreviews
{
    public function __construct(protected GeneralSettings $general) {}

    /**
     * Every email the tenant can preview, keyed by a URL-safe key
     *
     * @return Collection<string, array{group: string, name: string, render: Closure(): Renderable}>
     */
    public function all(): Collection
    {
        /** @var Collection<string, array{group: string, name: string, render: Closure(): Renderable}> $previews */
        $previews = collect([
            'users.password-setup' => [
                'group' => (string) __('site_settings.email_previews.groups.users'),
                'name' => (string) __('site_settings.email_previews.emails.password_setup'),
                'render' => fn () => (new ResetPassword('sample-token'))->toMail($this->sampleUser()),
            ],
            'settings.mail-test' => [
                'group' => (string) __('site_settings.email_previews.groups.settings'),
                'name' => (string) __('site_settings.email_previews.emails.mail_test'),
                'render' => fn () => new MailSettingsTestMail($this->general->site_name, (string) __('site_settings.mail.drivers.default')),
            ],
        ]);

        if ($this->tenantHas(TenantModule::Crm)) {
            Form::query()->orderBy('name')->each(function (Form $form) use ($previews) {
                $previews->put("crm.form-submitted.{$form->id}", [
                    'group' => (string) __('site_settings.email_previews.groups.crm'),
                    'name' => (string) __('site_settings.email_previews.emails.form_submitted', ['form' => $form->name]),
                    'render' => fn () => new FormSubmittedMail($this->sampleSubmission($form)),
                ]);
            });
        }

        return $previews;
    }

    /**
     * @return array{group: string, name: string, render: Closure(): Renderable}|null
     */
    public function find(string $key): ?array
    {
        return $this->all()->get($key);
    }

    protected function tenantHas(TenantModule $module): bool
    {
        $tenant = tenant();

        return $tenant instanceof Tenant && $tenant->hasModule($module);
    }

    protected function sampleUser(): User
    {
        return new User([
            'first_name' => 'Sam',
            'last_name' => 'Sample',
            'email' => 'sam@example.com',
        ]);
    }

    /**
     * The form's latest real submission if it has one, otherwise a made-up (unsaved) one with a
     * sample answer for each field
     */
    protected function sampleSubmission(Form $form): FormSubmission
    {
        $latest = $form->submissions()->where('is_spam', false)->latest('submitted_at')->first();

        if ($latest instanceof FormSubmission) {
            return $latest;
        }

        $submission = new FormSubmission([
            'is_spam' => false,
            'data' => new FormSubmissionData(
                answers: array_values($form->fields
                    ->reject(fn (FormField $field) => $field->type === FormFieldType::Hidden)
                    ->map(fn (FormField $field) => new FormSubmissionAnswer(
                        slug: $field->slug,
                        label: $field->name,
                        type: $field->type,
                        value: $this->sampleValue($field->type),
                    ))
                    ->all()),
            ),
            'submitted_at' => now(),
        ]);
        // Unsaved, so the "view submission" link points at a submission that doesn't exist
        $submission->id = 0;
        $submission->setRelation('form', $form);

        return $submission;
    }

    protected function sampleValue(FormFieldType $type): string|int
    {
        return match ($type) {
            FormFieldType::Email, FormFieldType::ContactEmail => 'sam@example.com',
            FormFieldType::Number => 42,
            FormFieldType::ContactTitle => 'Dr',
            FormFieldType::ContactFirstName => 'Sam',
            FormFieldType::ContactLastName => 'Sample',
            FormFieldType::ContactTelephone => '01234 567890',
            FormFieldType::Textarea => (string) __('site_settings.email_previews.sample.long_answer'),
            default => (string) __('site_settings.email_previews.sample.answer'),
        };
    }
}
