<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Cms\Layout;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Crm\Form;
use App\Models\Crm\OrganisationUnit;
use App\Services\Authorisation\Enums\CmsPermission;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));

    $this->template = Template::factory()->ofType(TemplateType::Layout)->create();
    TemplateField::factory()->for($this->template)->create(['type' => TemplateFieldType::CrmForm, 'name' => 'Contact form', 'slug' => 'form', 'order' => 0]);
    TemplateField::factory()->for($this->template)->create([
        'type' => TemplateFieldType::CrmOrganisationUnit,
        'name' => 'Office',
        'slug' => 'office',
        'order' => 1,
        'settings' => ['organisation_unit_type' => 'location'],
    ]);
});

/**
 * @param  array<string, mixed>  $content
 */
function saveCrmLayout(Template $template, array $content): TestResponse
{
    return test()->post(route('admin.cms.layouts.store'), [
        'template_id' => $template->id,
        'name' => 'Main',
        'content' => $content,
    ]);
}

test('a form and an organisation unit are stored by id', function () {
    $form = Form::factory()->create();
    $company = OrganisationUnit::factory()->create();
    $office = OrganisationUnit::factory()->create(['type' => OrganisationUnitType::Location, 'parent_id' => $company->id]);

    saveCrmLayout($this->template, ['form' => (string) $form->id, 'office' => (string) $office->id])
        ->assertSessionHasNoErrors();

    expect(Layout::query()->sole()->contentValues())->toBe(['form' => $form->id, 'office' => $office->id]);
});

test('the form must exist (and not be deleted), and the unit must be of the field\'s type', function () {
    $deleted = Form::factory()->create();
    $deleted->delete();
    $company = OrganisationUnit::factory()->create();

    saveCrmLayout($this->template, ['form' => $deleted->id, 'office' => $company->id])
        ->assertSessionHasErrors([
            'content.form' => __('validation.exists', ['attribute' => 'Contact form']),
            'content.office' => __('validation.exists', ['attribute' => 'Office']),
        ]);
});

test('the layout editor gets forms and units to choose from, but not without CRM', function () {
    Form::factory()->create(['name' => 'Enquiries']);
    $layout = Layout::factory()->create(['template_id' => $this->template->id]);

    $this->get(route('admin.cms.layouts.edit', $layout))
        ->assertInertia(fn (Assert $page) => $page->where('crmOptions.forms.0.label', 'Enquiries'));

    tenant()->update(['modules' => [TenantModule::Cms]]);

    $this->get(route('admin.cms.layouts.edit', $layout))
        ->assertInertia(fn (Assert $page) => $page->where('crmOptions', null));
});

test('when CRM is switched off later, CRM fields keep their values without being checked', function () {
    $form = Form::factory()->create();
    $layout = Layout::factory()->create(['template_id' => $this->template->id]);

    tenant()->update(['modules' => [TenantModule::Cms]]);

    // The id no longer needs to exist, as CRM isn't there to check against
    $this->put(route('admin.cms.layouts.update', $layout), [
        'template_id' => $this->template->id,
        'name' => $layout->name,
        'slug' => $layout->slug,
        'content' => ['form' => $form->id, 'office' => 999],
    ])->assertSessionHasNoErrors();

    expect($layout->refresh()->contentValues())->toBe(['form' => $form->id, 'office' => 999]);
});

describe('template builder', function () {
    test('CRM field types are offered with CRM, with the unit type setting', function () {
        $this->get(route('admin.cms.templates.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('fieldTypes', fn ($types) => collect($types)->firstWhere('value', 'crm_form')['needsModule'] === false)
                ->has('organisationUnitTypes', 3)
            );

        $this->post(route('admin.cms.templates.store'), [
            'type' => 'page',
            'name' => 'Contact',
            'fields' => [
                ['type' => 'crm_organisation_unit', 'name' => 'Office', 'slug' => 'office', 'settings' => ['organisation_unit_type' => 'department']],
                ['type' => 'crm_organisation_unit', 'name' => 'Bad', 'slug' => 'bad', 'settings' => ['organisation_unit_type' => 'galaxy']],
            ],
        ])->assertSessionHasErrors('fields.1.settings.organisation_unit_type');
    });

    test('without CRM, CRM field types aren\'t offered and can\'t be added', function () {
        tenant()->update(['modules' => [TenantModule::Cms]]);

        $this->get(route('admin.cms.templates.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('fieldTypes', fn ($types) => collect($types)->firstWhere('value', 'crm_form')['needsModule'] === true)
            );

        $this->post(route('admin.cms.templates.store'), [
            'type' => 'page',
            'name' => 'Contact',
            'fields' => [['type' => 'crm_form', 'name' => 'Form', 'slug' => 'form']],
        ])->assertSessionHasErrors([
            'fields.0.type' => __('cms.templates.validation.field_type_needs_module', ['field' => 'CRM form', 'module' => 'CRM']),
        ]);
    });
});
