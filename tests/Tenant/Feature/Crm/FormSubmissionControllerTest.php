<?php

use App\Enums\Crm\FormFieldType;
use App\Enums\TenantModule;
use App\Models\Crm\Contact;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Models\Crm\FormSubmission;
use App\Services\Authorisation\Enums\CrmPermission;
use Inertia\Testing\AssertableInertia as Assert;

describe('access', function () {
    test('users without the view permission cannot see submissions', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.crm.form-submissions.index'))->assertForbidden();
    });

    test('users with only the view permission can read submissions but not mark them as spam', function () {
        $this->actingAs($this->createUserWithPermissions([CrmPermission::ViewFormSubmissions]));
        $submission = FormSubmission::factory()->create();

        $this->get(route('admin.crm.form-submissions.index'))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $page->component('admin/crm/form-submission/Index')->has('submissions.data', 1);

                $labels = collect($page->toArray()['props']['submissions']['data'][0]['actions'])->pluck('label');
                expect($labels->all())->toBe([__('common.actions.view')]);
            });

        $this->get(route('admin.crm.form-submissions.show', $submission))->assertOk();
        $this->post(route('admin.crm.form-submissions.spam.store', $submission))->assertForbidden();
    });

    test('the pages return not found when the tenant does not have the CRM module', function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Tracker]]);

        $this->get(route('admin.crm.form-submissions.index'))->assertNotFound();
    });
});

describe('index', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('it can be filtered by form, contact and spam', function () {
        $form = Form::factory()->create();
        $contact = Contact::factory()->create();
        FormSubmission::factory()->for($form)->for($contact)->create();
        FormSubmission::factory()->for($form)->spam()->create();
        FormSubmission::factory()->create();

        $count = fn (array $filter) => count(
            $this->get(route('admin.crm.form-submissions.index', ['filter' => $filter]))
                ->inertiaProps('submissions.data')
        );

        expect($count([]))->toBe(3)
            ->and($count(['form_id' => $form->id]))->toBe(2)
            ->and($count(['contact_id' => $contact->id]))->toBe(1)
            ->and($count(['is_spam' => '1']))->toBe(1)
            ->and($count(['form_id' => $form->id, 'is_spam' => '0']))->toBe(1);
    });

    test('it can be filtered by an inclusive date range, newest first', function () {
        FormSubmission::factory()->create(['submitted_at' => '2026-10-01 09:00']);
        FormSubmission::factory()->create(['submitted_at' => '2026-10-10 23:59']);
        FormSubmission::factory()->create(['submitted_at' => '2026-10-20 08:00']);

        $this->get(route('admin.crm.form-submissions.index', ['filter' => ['submitted_from' => '2026-10-01', 'submitted_to' => '2026-10-10']]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions.data', 2)
                ->where('submissions.data.0.submitted_at', fn (string $date) => str_starts_with($date, '2026-10-10'))
            );
    });
});

describe('show', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('answers keep the labels they were submitted with after the form changes', function () {
        $form = Form::factory()->create(['marketing_email' => true]);
        $field = FormField::factory()->for($form)->ofType(FormFieldType::Text)->create(['name' => 'Company', 'slug' => 'company']);
        $form->load('fields');

        $submission = FormSubmission::factory()->answering($form, Contact::factory()->create())->create();

        $field->update(['name' => 'Organisation']);
        $form->fields()->delete();

        $this->get(route('admin.crm.form-submissions.show', $submission))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/crm/form-submission/Show')
                ->where('submission.data.answers.0.label', 'Company')
                ->where('submission.data.answers.0.type', 'text')
                ->has('submission.data.marketing.marketing_email')
            );
    });

    test('submissions of deleted forms can still be viewed', function () {
        $submission = FormSubmission::factory()->create();
        $submission->form->delete();

        $this->get(route('admin.crm.form-submissions.show', $submission))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('submission.data.form.deleted', true));
    });
});

describe('spam', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('a single submission can be marked and unmarked as spam', function () {
        $submission = FormSubmission::factory()->create();

        $this->post(route('admin.crm.form-submissions.spam.store', $submission))->assertRedirect();
        expect($submission->fresh()->is_spam)->toBeTrue();

        $this->delete(route('admin.crm.form-submissions.spam.destroy', $submission))->assertRedirect();
        expect($submission->fresh()->is_spam)->toBeFalse();
    });

    test('marking a contact as spam (or not) updates all of their submissions', function () {
        $contact = Contact::factory()->create();
        FormSubmission::factory()->for($contact)->count(2)->create();
        $other = FormSubmission::factory()->create();

        $this->post(route('admin.crm.contacts.spam.store', $contact));

        expect($contact->formSubmissions()->where('is_spam', true)->count())->toBe(2)
            ->and($other->fresh()->is_spam)->toBeFalse();

        $this->delete(route('admin.crm.contacts.spam.destroy', $contact));

        expect($contact->formSubmissions()->where('is_spam', true)->count())->toBe(0);
    });
});

describe('contact page', function () {
    test('it lists the contact\'s latest submissions', function () {
        $this->actingAs($this->createUserWithPermissions([CrmPermission::ViewContacts, CrmPermission::ViewFormSubmissions]));
        $contact = Contact::factory()->create();
        FormSubmission::factory()->for($contact)->count(12)->create();

        $this->get(route('admin.crm.contacts.show', $contact))
            ->assertInertia(fn (Assert $page) => $page
                ->has('submissions.data', 10)
                ->where('submissionsCount', 12)
            );
    });

    test('it leaves submissions out for users who cannot see them', function () {
        $this->actingAs($this->createUserWithPermissions([CrmPermission::ViewContacts]));
        $contact = Contact::factory()->create();

        $this->get(route('admin.crm.contacts.show', $contact))
            ->assertInertia(fn (Assert $page) => $page->where('submissions', null));
    });
});
