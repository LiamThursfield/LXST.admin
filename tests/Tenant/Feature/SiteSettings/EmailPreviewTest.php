<?php

use App\Data\Crm\FormSubmissionAnswer;
use App\Data\Crm\FormSubmissionData;
use App\Enums\Crm\FormFieldType;
use App\Enums\TenantModule;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Models\Crm\FormSubmission;
use App\Services\Authorisation\Enums\SettingsPermission;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    tenant()->update(['modules' => [TenantModule::Crm]]);
    $this->actingAs($this->createUserWithPermissions([SettingsPermission::ViewSettings]));
});

test('the previewable emails are listed by group, with one per CRM form', function () {
    $form = Form::factory()->create(['name' => 'Contact us']);

    $this->get(route('admin.site-settings.email-previews.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site-settings/EmailPreviews')
            ->where('groups.0.label', __('site_settings.email_previews.groups.users'))
            ->where('groups.1.emails.0.key', 'settings.mail-test')
            ->where('groups.2.emails.0.name', __('site_settings.email_previews.emails.form_submitted', ['form' => 'Contact us']))
            ->where('groups.2.emails.0.url', route('admin.site-settings.email-previews.show', "crm.form-submitted.{$form->id}"))
        );
});

test('the password setup and mail test emails can be previewed', function () {
    $this->get(route('admin.site-settings.email-previews.show', 'users.password-setup'))
        ->assertOk()
        ->assertSee('reset-password/sample-token', false);

    $this->get(route('admin.site-settings.email-previews.show', 'settings.mail-test'))
        ->assertOk()
        ->assertSee(__('site_settings.mail.drivers.default'), false);
});

test('a form\'s email uses its latest real submission', function () {
    $form = Form::factory()->create();
    FormSubmission::factory()->for($form)->create([
        'data' => new FormSubmissionData(answers: [new FormSubmissionAnswer('message', 'Message', FormFieldType::Textarea, 'A real message')]),
        'submitted_at' => now(),
    ]);
    FormSubmission::factory()->for($form)->spam()->create([
        'data' => new FormSubmissionData(answers: [new FormSubmissionAnswer('message', 'Message', FormFieldType::Textarea, 'Buy pills')]),
        'submitted_at' => now()->addMinute(),
    ]);

    $this->get(route('admin.site-settings.email-previews.show', "crm.form-submitted.{$form->id}"))
        ->assertOk()
        ->assertSee('A real message')
        ->assertDontSee('Buy pills');
});

test('a form without submissions is previewed with sample answers, leaving out hidden fields', function () {
    $form = Form::factory()->create();
    FormField::factory()->for($form)->create(['type' => FormFieldType::ContactEmail, 'name' => 'Your email', 'order' => 0]);
    FormField::factory()->for($form)->create(['type' => FormFieldType::Hidden, 'name' => 'Campaign', 'order' => 1]);

    $this->get(route('admin.site-settings.email-previews.show', "crm.form-submitted.{$form->id}"))
        ->assertOk()
        ->assertSee('Your email')
        ->assertSee('sam@example.com')
        ->assertDontSee('Campaign');

    expect(FormSubmission::query()->count())->toBe(0);
});

test('CRM emails need the CRM module', function () {
    $form = Form::factory()->create();
    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->get(route('admin.site-settings.email-previews.index'))
        ->assertInertia(fn (Assert $page) => $page->has('groups', 2));

    $this->get(route('admin.site-settings.email-previews.show', "crm.form-submitted.{$form->id}"))->assertNotFound();
});

test('an unknown email isn\'t found', function () {
    $this->get(route('admin.site-settings.email-previews.show', 'nope'))->assertNotFound();
});

test('email previews need permission to view settings', function () {
    $this->actingAs($this->createUserWithPermissions([]));

    $this->get(route('admin.site-settings.email-previews.index'))->assertForbidden();
    $this->get(route('admin.site-settings.email-previews.show', 'settings.mail-test'))->assertForbidden();
});
