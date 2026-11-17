<?php

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Crm\FormFieldType;
use App\Mail\Crm\FormSubmittedMail;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Models\Crm\FormSubmission;
use App\Services\Authorisation\Enums\CrmPermission;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

require_once __DIR__.'/../Api/Mcp/helpers.php';

/**
 * A form with one select field, as the builder sends it
 *
 * @param  array<string, mixed>  $settings
 * @return array<string, mixed>
 */
function formWithSelect(array $settings): array
{
    return [
        'name' => 'Contact',
        'slug' => 'contact',
        'fields' => [
            ['id' => null, 'type' => 'select', 'name' => 'Enquiry type', 'slug' => '', 'is_required' => true, 'settings' => $settings],
        ],
    ];
}

/**
 * @return list<array{label: string, value: string}>
 */
function enquiryOptions(): array
{
    return [['label' => 'Web design', 'value' => 'web-design'], ['label' => 'Hosting', 'value' => 'hosting']];
}

/**
 * @param  array<string, mixed>  $answers
 */
function submitEnquiry(array $answers): mixed
{
    return test()->postJson(route('api.v1.crm.forms.submissions.store', 'contact'), [
        ...$answers,
        'website_url' => '',
        // From a form loaded a minute ago, so it isn't flagged as too fast
        'form_token' => Crypt::encryptString((string) now()->subMinute()->getTimestamp()),
    ]);
}

describe('the form builder', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('a select field keeps only its options and default', function () {
        $options = [['label' => 'Web design', 'value' => 'web-design', 'uid' => 4], ['label' => 'Hosting', 'value' => 'hosting']];

        $this->post(route('admin.crm.forms.store'), formWithSelect(['options' => $options, 'default' => 'hosting', 'max_length' => 10]))
            ->assertSessionHasNoErrors();

        $field = Form::query()->sole()->fields->sole();

        expect($field->type)->toBe(FormFieldType::Select)
            ->and($field->slug)->toBe('enquiry_type')
            ->and($field->settings->options)->toBe(enquiryOptions())
            ->and($field->settings->default)->toBe('hosting')
            ->and($field->settings->max_length)->toBeNull();
    });

    test('a select field needs at least one option', function (mixed $options) {
        $this->post(route('admin.crm.forms.store'), formWithSelect(['options' => $options]))
            ->assertSessionHasErrors(['fields.0.settings.options' => __('crm.forms.validation.options_required')]);
    })->with([
        'missing' => [null],
        'empty' => [[]],
    ]);

    test('option values must be unique and alpha-dash, and labels are required', function () {
        $this->post(route('admin.crm.forms.store'), formWithSelect(['options' => [
            ['label' => 'Web design', 'value' => 'web-design'],
            ['label' => 'Web design again', 'value' => 'web-design'],
        ]]))->assertSessionHasErrors(['fields.0.settings.options' => __('crm.forms.validation.duplicate_option_value')]);

        $this->post(route('admin.crm.forms.store'), formWithSelect(['options' => [
            ['label' => '', 'value' => 'web design'],
        ]]))->assertSessionHasErrors(['fields.0.settings.options.0.label', 'fields.0.settings.options.0.value']);
    });

    test('the default must be one of the options', function () {
        $this->post(route('admin.crm.forms.store'), formWithSelect(['options' => enquiryOptions(), 'default' => 'seo']))
            ->assertSessionHasErrors(['fields.0.settings.default' => __('crm.forms.validation.default_not_an_option')]);
    });

    test('other field types drop any options they are sent', function () {
        $data = formWithSelect(['options' => enquiryOptions()]);
        $data['fields'][0]['type'] = 'text';

        $this->post(route('admin.crm.forms.store'), $data)->assertSessionHasNoErrors();

        expect(Form::query()->sole()->fields->sole()->settings)->toBeNull();
    });
});

describe('the public API', function () {
    beforeEach(function () {
        Mail::fake();
        config(['api.form_submissions.rate_limit_per_minute' => 10000]);

        $this->form = Form::factory()->create(['slug' => 'contact', 'email_recipients' => ['hello@example.test']]);
        FormField::factory()->for($this->form)->select(['Web design' => 'web-design', 'Hosting' => 'hosting'], 'hosting')->required()->create();
    });

    test('the form definition lists the options and default', function () {
        $this->getJson(route('api.v1.crm.forms.show', 'contact'))
            ->assertOk()
            ->assertJsonPath('data.fields.0.type', 'select')
            ->assertJsonPath('data.fields.0.settings', ['options' => enquiryOptions(), 'default' => 'hosting']);
    });

    test('a submission saves the chosen value with its label, and the email shows the label', function () {
        submitEnquiry(['enquiry_type' => 'web-design'])->assertCreated();

        $answer = FormSubmission::query()->sole()->data->answers[0];

        expect($answer->value)->toBe('web-design')
            ->and($answer->option_label)->toBe('Web design');

        Mail::assertQueued(FormSubmittedMail::class, fn (FormSubmittedMail $mail) => str_contains($mail->render(), 'Web design'));
    });

    test('only one of the option values is accepted', function (mixed $answer) {
        submitEnquiry(['enquiry_type' => $answer])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['enquiry_type']);

        expect(FormSubmission::query()->count())->toBe(0);
    })->with([
        'a label' => ['Web design'],
        'an unknown value' => ['seo'],
        'a list' => [['web-design']],
        'nothing, when required' => [null],
    ]);
});

test('an assistant can create a form with a select field', function () {
    $token = mcpToken(mcpUser($this), [ApiTokenAbility::McpCrmForms]);

    $result = mcpCall($this, $token, 'create_form', [
        'name' => 'Contact',
        'slug' => 'contact',
        'fields' => [['type' => 'select', 'name' => 'Enquiry type', 'settings' => ['options' => enquiryOptions()]]],
    ]);

    expect($result['isError'])->toBeFalse()
        ->and(Form::query()->sole()->fields->sole()->settings->optionValues())->toBe(['web-design', 'hosting']);

    $refused = mcpCall($this, $token, 'create_form', [
        'name' => 'Other',
        'slug' => 'other',
        'fields' => [['type' => 'select', 'name' => 'Enquiry type']],
    ]);

    expect($refused['isError'])->toBeTrue();
});
