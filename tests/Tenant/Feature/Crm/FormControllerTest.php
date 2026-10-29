<?php

use App\Enums\Crm\FormFieldType;
use App\Enums\TenantModule;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Services\Authorisation\Enums\CrmPermission;
use Inertia\Testing\AssertableInertia as Assert;

function validFormData(array $overrides = []): array
{
    return [
        'name' => 'Contact us',
        'slug' => '',
        'email_recipients' => ['sales@example.test', ''],
        'marketing_email' => true,
        'marketing_sms' => false,
        'marketing_telephone' => false,
        'submit_button_text' => 'Send',
        'redirect_url' => '',
        'success_message' => 'Thanks!',
        'fields' => [
            ['id' => null, 'type' => 'crm_email', 'name' => 'Email address', 'slug' => '', 'is_required' => true, 'settings' => []],
            ['id' => null, 'type' => 'textarea', 'name' => 'Your message', 'slug' => '', 'is_required' => true, 'settings' => ['max_length' => 500, 'rows' => 4, 'min' => 3]],
        ],
        ...$overrides,
    ];
}

describe('access', function () {
    test('users without the view permission cannot see the index', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.crm.forms.index'))->assertForbidden();
    });

    test('users with only the view permission can see forms but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([CrmPermission::ViewForms]));
        $form = Form::factory()->create();

        $this->get(route('admin.crm.forms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/crm/form/Index')
                ->has('forms.data', 1)
                ->where('canCreate', false)
            );

        $this->get(route('admin.crm.forms.show', $form))->assertOk();
        $this->post(route('admin.crm.forms.store'), validFormData())->assertForbidden();
        $this->delete(route('admin.crm.forms.destroy', $form))->assertForbidden();
    });

    test('the pages return not found when the tenant does not have the CRM module', function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Tracker]]);

        $this->get(route('admin.crm.forms.index'))->assertNotFound();
    });
});

describe('store', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('it creates a form with its fields in order, generating slugs and keeping only relevant settings', function () {
        $this->post(route('admin.crm.forms.store'), validFormData())
            ->assertRedirect(route('admin.crm.forms.index'));

        $form = Form::query()->sole();
        $fields = $form->fields;

        expect($form->slug)->toBe('contact-us')
            ->and($form->email_recipients)->toBe(['sales@example.test'])
            ->and($fields->pluck('slug')->all())->toBe(['email_address', 'your_message'])
            ->and($fields->pluck('order')->all())->toBe([0, 1])
            ->and($fields[0]->type)->toBe(FormFieldType::ContactEmail)
            ->and($fields[0]->settings)->toBeNull()
            ->and($fields[1]->settings->max_length)->toBe(500)
            ->and($fields[1]->settings->rows)->toBe(4)
            // min doesn't apply to a textarea, so it is dropped
            ->and($fields[1]->settings->min)->toBeNull();
    });

    test('field keys must be unique within the form', function () {
        $data = validFormData(['fields' => [
            ['id' => null, 'type' => 'text', 'name' => 'Name', 'slug' => 'name', 'is_required' => false, 'settings' => []],
            ['id' => null, 'type' => 'text', 'name' => 'Name', 'slug' => '', 'is_required' => false, 'settings' => []],
        ]]);

        $this->post(route('admin.crm.forms.store'), $data)->assertSessionHasErrors('fields.1.slug');
    });

    test('field errors name the field as the builder does', function () {
        $data = validFormData(['fields' => [
            ['id' => null, 'type' => 'text', 'name' => '', 'slug' => 'message', 'is_required' => false, 'settings' => []],
        ]]);

        $this->post(route('admin.crm.forms.store'), $data)->assertSessionHasErrors([
            'fields.0.name' => __('validation.required', ['attribute' => __('crm.forms.builder.name')]),
        ]);
    });

    test('field keys used by the form itself are reserved', function () {
        foreach (['website_url', 'form_token', 'marketing_email'] as $reserved) {
            $data = validFormData(['fields' => [
                ['id' => null, 'type' => 'text', 'name' => 'Field', 'slug' => $reserved, 'is_required' => false, 'settings' => []],
            ]]);

            $this->post(route('admin.crm.forms.store'), $data)->assertSessionHasErrors('fields.0.slug');
        }
    });

    test('each contact detail can only be asked for once', function () {
        $data = validFormData(['fields' => [
            ['id' => null, 'type' => 'crm_email', 'name' => 'Email', 'slug' => 'email', 'is_required' => true, 'settings' => []],
            ['id' => null, 'type' => 'crm_email', 'name' => 'Email again', 'slug' => 'email_again', 'is_required' => false, 'settings' => []],
        ]]);

        $this->post(route('admin.crm.forms.store'), $data)->assertSessionHasErrors('fields');
    });

    test('a number field maximum cannot be below its minimum, but either can be left blank', function () {
        $field = fn (array $settings) => validFormData(['fields' => [
            ['id' => null, 'type' => 'number', 'name' => 'Guests', 'slug' => 'guests', 'is_required' => false, 'settings' => $settings],
        ]]);

        $this->post(route('admin.crm.forms.store'), $field(['min' => 5, 'max' => 2]))
            ->assertSessionHasErrors('fields.0.settings.max');

        $this->post(route('admin.crm.forms.store'), $field(['min' => null, 'max' => 10]))
            ->assertSessionHasNoErrors();
    });

    test('a form slug cannot reuse a deleted form\'s slug, and generated slugs avoid it', function () {
        Form::factory()->create(['name' => 'Contact us', 'slug' => 'contact-us'])->delete();

        $this->post(route('admin.crm.forms.store'), validFormData(['slug' => 'contact-us']))
            ->assertSessionHasErrors('slug');

        $this->post(route('admin.crm.forms.store'), validFormData())->assertSessionHasNoErrors();

        expect(Form::query()->sole()->slug)->toBe('contact-us-2');
    });
});

describe('update', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('fields are updated by id, reordered, added and removed', function () {
        $form = Form::factory()->create();
        $first = FormField::factory()->for($form)->create(['name' => 'First', 'slug' => 'first', 'order' => 0]);
        $second = FormField::factory()->for($form)->create(['name' => 'Second', 'slug' => 'second', 'order' => 1]);
        $removed = FormField::factory()->for($form)->create(['name' => 'Removed', 'slug' => 'removed', 'order' => 2]);

        $this->put(route('admin.crm.forms.update', $form), validFormData(['fields' => [
            // Swapped order and swapped keys
            ['id' => $second->id, 'type' => 'text', 'name' => 'Second', 'slug' => 'first', 'is_required' => false, 'settings' => []],
            ['id' => $first->id, 'type' => 'text', 'name' => 'First', 'slug' => 'second', 'is_required' => true, 'settings' => []],
            ['id' => null, 'type' => 'number', 'name' => 'New', 'slug' => 'new', 'is_required' => false, 'settings' => ['min' => 1]],
        ]]))->assertRedirect(route('admin.crm.forms.index'));

        $fields = $form->fresh()->fields;

        expect($fields->pluck('id')->take(2)->all())->toBe([$second->id, $first->id])
            ->and($fields->pluck('slug')->all())->toBe(['first', 'second', 'new'])
            ->and($fields[1]->is_required)->toBeTrue()
            ->and($fields[2]->settings->min)->toBe(1)
            ->and(FormField::query()->find($removed->id))->toBeNull();
    });

    test('fields from another form cannot be updated', function () {
        $form = Form::factory()->create();
        $otherField = FormField::factory()->create();

        $this->put(route('admin.crm.forms.update', $form), validFormData(['fields' => [
            ['id' => $otherField->id, 'type' => 'text', 'name' => 'Hijack', 'slug' => 'hijack', 'is_required' => false, 'settings' => []],
        ]]))->assertSessionHasErrors('fields.0.id');
    });
});

test('deleting a form soft deletes it', function () {
    $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    $form = Form::factory()->create();

    $this->delete(route('admin.crm.forms.destroy', $form))->assertRedirect();

    expect($form->fresh()->trashed())->toBeTrue();
});
