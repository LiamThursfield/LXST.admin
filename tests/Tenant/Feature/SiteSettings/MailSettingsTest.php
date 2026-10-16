<?php

use App\Mail\Settings\MailSettingsTestMail;
use App\Models\Settings\MailSettings;
use App\Services\Authorisation\Enums\SettingsPermission;
use App\Services\Mail\TenantMailBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions(SettingsPermission::cases()));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function smtpSettings(array $overrides = []): array
{
    return [
        'driver' => 'smtp',
        'from_address' => 'hello@acme.test',
        'from_name' => 'Acme',
        'smtp_host' => 'smtp.acme.test',
        'smtp_port' => 587,
        'smtp_username' => 'mailer',
        'smtp_password' => 'secret-password',
        ...$overrides,
    ];
}

/** Applies the tenant's saved mail settings, as happens when tenancy starts for a request or job */
function bootstrapTenantMail(): TenantMailBootstrapper
{
    app()->forgetInstance(MailSettings::class);
    $bootstrapper = app(TenantMailBootstrapper::class);
    $bootstrapper->bootstrap(tenant());

    return $bootstrapper;
}

describe('saving', function () {
    test('with no mailer set up, the platform\'s mailer is used', function () {
        $this->get(route('admin.site-settings.mail.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/site-settings/Mail')
                ->where('settings.driver', null)
                ->where('savedSecrets', ['smtp_password' => false, 'mailgun_secret' => false, 'ses_secret' => false])
            );

        bootstrapTenantMail();

        expect(config('mail.default'))->not->toBe(TenantMailBootstrapper::MAILER);
    });

    test('SMTP settings are saved, with the password encrypted and never sent back to the browser', function () {
        $this->put(route('admin.site-settings.mail.update'), smtpSettings())
            ->assertRedirect(route('admin.site-settings.mail.edit'))
            ->assertSessionHas('success', __('site_settings.mail.updated'));

        $stored = DB::connection('tenant')->table('settings')->where('group', 'mail')->where('name', 'smtp_password')->value('payload');

        expect(app(MailSettings::class)->smtp_password)->toBe('secret-password')
            ->and((string) $stored)->not->toContain('secret-password');

        $this->get(route('admin.site-settings.mail.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.smtp_host', 'smtp.acme.test')
                ->missing('settings.smtp_password')
                ->where('savedSecrets.smtp_password', true)
            );
    });

    test('leaving a secret empty keeps the saved one, and choosing another driver clears the old one\'s details', function () {
        $this->put(route('admin.site-settings.mail.update'), smtpSettings());
        $this->put(route('admin.site-settings.mail.update'), smtpSettings(['smtp_host' => 'mail.acme.test', 'smtp_password' => '']));

        expect(app(MailSettings::class)->smtp_password)->toBe('secret-password')
            ->and(app(MailSettings::class)->smtp_host)->toBe('mail.acme.test');

        $this->put(route('admin.site-settings.mail.update'), [
            'driver' => 'mailgun',
            'from_address' => 'hello@acme.test',
            'mailgun_domain' => 'mg.acme.test',
            'mailgun_secret' => 'key-123',
            'mailgun_endpoint' => 'api.eu.mailgun.net',
        ])->assertSessionHasNoErrors();

        $settings = app(MailSettings::class);

        expect($settings->driver)->toBe('mailgun')
            ->and($settings->mailgun_secret)->toBe('key-123')
            ->and($settings->smtp_host)->toBeNull()
            ->and($settings->smtp_password)->toBeNull();
    });

    test('the chosen driver\'s details are required, including a secret when none is saved', function () {
        $this->put(route('admin.site-settings.mail.update'), ['driver' => 'smtp'])
            ->assertSessionHasErrors(['from_address', 'smtp_host', 'smtp_port']);

        $this->put(route('admin.site-settings.mail.update'), ['driver' => 'mailgun', 'from_address' => 'hello@acme.test'])
            ->assertSessionHasErrors(['mailgun_domain', 'mailgun_secret', 'mailgun_endpoint']);

        $this->put(route('admin.site-settings.mail.update'), [
            'driver' => 'ses',
            'from_address' => 'hello@acme.test',
            'ses_key' => 'AKIA123',
            'ses_secret' => 'secret',
            'ses_region' => 'London',
        ])->assertSessionHasErrors(['ses_region' => __('site_settings.mail.validation.ses_region')]);
    });
});

describe('sending', function () {
    test('the tenant\'s email is sent through their own mailer and sender, and reverts after', function () {
        $this->put(route('admin.site-settings.mail.update'), smtpSettings());
        $originalDefault = config('mail.default');

        $bootstrapper = bootstrapTenantMail();

        expect(config('mail.default'))->toBe(TenantMailBootstrapper::MAILER)
            ->and(config('mail.mailers.tenant'))->toMatchArray([
                'transport' => 'smtp',
                'host' => 'smtp.acme.test',
                'port' => 587,
                'password' => 'secret-password',
            ])
            ->and(config('mail.from'))->toBe(['address' => 'hello@acme.test', 'name' => 'Acme']);

        $bootstrapper->revert();

        expect(config('mail.default'))->toBe($originalDefault);
    });

    test('a test email can be sent to check the mailer works', function () {
        Mail::fake();

        $this->post(route('admin.site-settings.mail.test'), ['to' => 'me@acme.test'])
            ->assertSessionHas('success', __('site_settings.mail.test_sent', ['to' => 'me@acme.test']));

        Mail::assertSent(MailSettingsTestMail::class, fn (MailSettingsTestMail $mail) => $mail->hasTo('me@acme.test'));
    });

    test('when the test email can\'t be sent, the error is shown', function () {
        // Nothing listens on port 1, so the connection is refused straight away
        $this->put(route('admin.site-settings.mail.update'), smtpSettings(['smtp_host' => '127.0.0.1', 'smtp_port' => 1]));
        bootstrapTenantMail();

        $this->post(route('admin.site-settings.mail.test'), ['to' => 'me@acme.test'])
            ->assertSessionHas('error', fn (string $error) => str_starts_with($error, __('site_settings.mail.test_failed', ['error' => ''])));
    });
});

test('users who can only view settings cannot change mail settings or send test emails', function () {
    $this->actingAs($this->createUserWithPermissions([SettingsPermission::ViewSettings]));

    $this->get(route('admin.site-settings.mail.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('canManage', false));

    $this->put(route('admin.site-settings.mail.update'), smtpSettings())->assertForbidden();
    $this->post(route('admin.site-settings.mail.test'), ['to' => 'me@acme.test'])->assertForbidden();
});
