<?php

use App\Data\Crm\FormFieldSettings;
use App\Enums\Crm\FormFieldType;
use App\Enums\TenantModule;
use App\Mail\Crm\FormSubmittedMail;
use App\Models\Crm\Contact;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Models\Crm\FormSubmission;
use App\Services\Crm\FormSpamCheck;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();

    // Only the rate limiting test cares about the limit; the test cache (Redis) persists between runs
    config(['api.form_submissions.rate_limit_per_minute' => 10000]);

    $this->form = Form::factory()->create([
        'slug' => 'contact-us',
        'email_recipients' => ['sales@example.test'],
        'marketing_email' => true,
        'marketing_sms' => false,
        'marketing_telephone' => false,
        'success_message' => 'Thanks!',
        'redirect_url' => 'https://example.test/thanks',
    ]);

    $fields = [
        [FormFieldType::ContactEmail, 'Email', 'email', true, null],
        [FormFieldType::ContactFirstName, 'First name', 'first_name', true, null],
        [FormFieldType::Text, 'Company', 'company', false, new FormFieldSettings(max_length: 10)],
        [FormFieldType::Number, 'Guests', 'guests', false, new FormFieldSettings(min: 1, max: 5)],
        [FormFieldType::Textarea, 'Message', 'message', true, null],
    ];

    foreach ($fields as $order => [$type, $name, $slug, $required, $settings]) {
        FormField::factory()->for($this->form)->create(compact('type', 'name', 'slug', 'order', 'settings') + ['is_required' => $required]);
    }

    // A token from a form loaded a minute ago, so submissions aren't flagged as too fast
    $this->token = Crypt::encryptString((string) now()->subMinute()->getTimestamp());
});

function submission(array $overrides = []): array
{
    return [
        'email' => 'jane@example.test',
        'first_name' => 'Jane',
        'company' => 'Acme',
        'guests' => 2,
        'message' => 'Hello there',
        'marketing_email' => true,
        'marketing_sms' => true,
        'website_url' => '',
        'form_token' => test()->token,
        ...$overrides,
    ];
}

function submit(array $data)
{
    return test()->postJson(route('api.v1.crm.forms.submissions.store', 'contact-us'), $data);
}

describe('form definition', function () {
    test('it returns the fields, marketing questions and spam protection details', function () {
        $this->getJson(route('api.v1.crm.forms.show', 'contact-us'))
            ->assertOk()
            ->assertJsonPath('data.slug', 'contact-us')
            ->assertJsonCount(5, 'data.fields')
            ->assertJsonPath('data.fields.0', [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'crm_email',
                'required' => true,
                'settings' => [],
            ])
            ->assertJsonPath('data.fields.2.settings', ['max_length' => 10])
            ->assertJsonPath('data.marketing', ['marketing_email'])
            ->assertJsonPath('data.spam_protection.honeypot_field', 'website_url')
            ->assertJsonPath('data.spam_protection.token_field', 'form_token')
            ->assertJsonStructure(['data' => ['spam_protection' => ['token']]]);
    });

    test('unknown and deleted forms return a JSON 404', function () {
        $this->getJson(route('api.v1.crm.forms.show', 'nope'))->assertNotFound();

        $this->form->delete();
        $this->getJson(route('api.v1.crm.forms.show', 'contact-us'))->assertNotFound();
    });

    test('forms are not found when the tenant does not have the CRM module', function () {
        tenant()->update(['modules' => [TenantModule::Tracker]]);

        $this->getJson(route('api.v1.crm.forms.show', 'contact-us'))->assertNotFound();
    });
});

describe('submitting', function () {
    test('it saves the answers, creates the contact and emails the recipients', function () {
        submit(submission())
            ->assertCreated()
            ->assertExactJson(['data' => ['message' => 'Thanks!', 'redirect_url' => 'https://example.test/thanks']]);

        $submission = FormSubmission::query()->sole();
        $contact = Contact::query()->sole();

        expect($submission->contact_id)->toBe($contact->id)
            ->and($submission->is_spam)->toBeFalse()
            ->and(collect($submission->data->answers)->pluck('value', 'label')->all())->toBe([
                'Email' => 'jane@example.test',
                'First name' => 'Jane',
                'Company' => 'Acme',
                'Guests' => 2,
                'Message' => 'Hello there',
            ])
            // Only the consent question the form asked is recorded
            ->and($submission->data->marketing)->toBe(['marketing_email' => true])
            ->and($contact->first_name)->toBe('Jane')
            ->and($contact->marketing_email)->toBeTrue()
            ->and($contact->marketing_sms)->toBeFalse();

        Mail::assertQueued(FormSubmittedMail::class, fn (FormSubmittedMail $mail) => $mail->hasTo('sales@example.test'));
    });

    test('an existing contact is updated, and a deleted one restored', function () {
        $contact = Contact::factory()->create(['email' => 'jane@example.test', 'first_name' => 'Old', 'marketing_sms' => true]);
        $contact->delete();

        submit(submission())->assertCreated();

        $contact->refresh();

        expect($contact->trashed())->toBeFalse()
            ->and($contact->first_name)->toBe('Jane')
            ->and($contact->marketing_email)->toBeTrue()
            // The form didn't ask about SMS, so the contact's answer is untouched
            ->and($contact->marketing_sms)->toBeTrue()
            ->and(Contact::query()->count())->toBe(1);
    });

    test('a spam contact\'s submission is marked as spam and not emailed', function () {
        Contact::factory()->spam()->create(['email' => 'jane@example.test']);

        submit(submission())->assertCreated();

        expect(FormSubmission::query()->sole()->is_spam)->toBeTrue();
        Mail::assertNothingQueued();
    });

    test('a form without a contact email field saves the submission without a contact', function () {
        $this->form->fields()->where('slug', 'email')->delete();

        submit(submission(['email' => null]))->assertCreated();

        expect(FormSubmission::query()->sole()->contact_id)->toBeNull()
            ->and(Contact::query()->count())->toBe(0);
    });

    test('answers are validated against each field, using the field labels', function () {
        submit(submission(['first_name' => '', 'company' => 'Far too long a name', 'guests' => 9, 'email' => 'nope']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'company', 'guests', 'email'])
            ->assertJsonPath('errors.first_name.0', __('validation.required', ['attribute' => 'First name']));

        expect(FormSubmission::query()->count())->toBe(0);
    });

    test('submissions are rate limited more strictly than the rest of the API', function () {
        config(['api.form_submissions.rate_limit_per_minute' => 1]);

        submit(submission())->assertCreated();
        submit(submission())->assertTooManyRequests();
    });
});

describe('spam check', function () {
    test('a filled honeypot is saved as spam, without touching contacts or emailing, and looks like success', function () {
        submit(submission(['website_url' => 'https://spam.example']))
            ->assertCreated()
            ->assertJsonPath('data.message', 'Thanks!');

        expect(FormSubmission::query()->sole()->is_spam)->toBeTrue()
            ->and(FormSubmission::query()->sole()->contact_id)->toBeNull()
            ->and(Contact::query()->count())->toBe(0);

        Mail::assertNothingQueued();
    });

    test('submitting too soon after loading the form is spam', function () {
        submit(submission(['form_token' => app(FormSpamCheck::class)->issueToken()]))->assertCreated();

        expect(FormSubmission::query()->sole()->is_spam)->toBeTrue();
    });

    test('a tampered token is spam', function () {
        submit(submission(['form_token' => 'not-a-real-token']))->assertCreated();

        expect(FormSubmission::query()->sole()->is_spam)->toBeTrue();
    });

    test('a missing token is not treated as spam, so sites that don\'t send it still work', function () {
        submit(submission(['form_token' => null]))->assertCreated();

        expect(FormSubmission::query()->sole()->is_spam)->toBeFalse();
    });
});

test('the notification email lists the answers and links to the submission', function () {
    $submission = FormSubmission::factory()->answering($this->form->load('fields'), Contact::factory()->create())->create();

    $mail = new FormSubmittedMail($submission);

    $mail->assertSeeInHtml('Company')
        ->assertSeeInHtml(route('admin.crm.form-submissions.show', $submission));

    expect($mail->envelope()->subject)->toBe(__('crm.submissions.mail.subject', ['form' => $this->form->name]));
});
