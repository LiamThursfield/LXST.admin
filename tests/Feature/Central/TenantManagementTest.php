<?php

use App\Enums\TenantModule;
use App\Enums\TenantSetupStatus;
use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorisation\Enums\Role;
use App\Services\Central\TenantDatabase;
use App\Services\Central\TenantProvisioner;
use App\Services\Cms\Api\ContentVersion;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Jobs\CreateDatabase;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tenantInput(array $overrides = []): array
{
    return [
        'name' => 'acme',
        'domain' => 'acme.test',
        'modules' => ['cms', 'crm'],
        ...$overrides,
    ];
}

describe('creating', function () {
    test('the create page offers every module', function () {
        $this->get(route('central.admin.tenants.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/tenant/Create')
                ->has('modules', count(TenantModule::cases()))
            );
    });

    test('a tenant is saved with its domain, then set up in the background', function () {
        Queue::fake();

        $this->post(route('central.admin.tenants.store'), tenantInput([
            'admin_first_name' => 'Ada',
            'admin_last_name' => 'Lovelace',
            'admin_email' => 'ada@example.com',
        ]))->assertSessionHas('success', __('tenants.flash.created', ['name' => 'acme']));

        $tenant = Tenant::query()->with('domains')->sole();

        expect($tenant->setup_status)->toBe(TenantSetupStatus::SettingUp)
            ->and($tenant->setup_admin)->toEqual(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com'])
            ->and($tenant->domains->pluck('domain')->all())->toBe(['acme.test'])
            ->and(app(TenantDatabase::class)->exists($tenant))->toBeFalse();

        Queue::assertPushed(ProvisionTenant::class, fn (ProvisionTenant $job) => $job->tenant->is($tenant));

        $this->get(route('central.admin.tenants.show', $tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.data.setup_status.value', 'setting_up')
                ->where('tenant.data.setup_admin_email', 'ada@example.com')
            );
    });

    test('a tenant is provisioned with its database, domain, modules and first admin, who is invited on the tenant\'s domain', function () {
        $response = $this->post(route('central.admin.tenants.store'), tenantInput([
            'domain' => ' Acme.TEST ',
            'admin_first_name' => 'Ada',
            'admin_last_name' => 'Lovelace',
            'admin_email' => 'ada@example.com',
        ]));

        $tenant = Tenant::query()->with('domains')->sole();

        $response->assertRedirect(route('central.admin.tenants.show', $tenant));

        expect($tenant->name)->toBe('acme')
            ->and($tenant->setup_status)->toBe(TenantSetupStatus::Ready)
            ->and($tenant->setup_admin)->toBeNull()
            ->and($tenant->modules->all())->toBe([TenantModule::Cms, TenantModule::Crm])
            ->and($tenant->domains->pluck('domain')->all())->toBe(['acme.test'])
            ->and($tenant->database()->manager()->databaseExists($tenant->database()->getName()))->toBeTrue();

        $tenant->run(function () {
            $admin = User::query()->sole();

            expect($admin->email)->toBe('ada@example.com')
                ->and($admin->hasRole(Role::SuperAdmin->value))->toBeTrue();
        });

        /** @var ArrayTransport $transport */
        $transport = Mail::mailer()->getSymfonyTransport();
        $email = $transport->messages()->sole();

        expect($email->getEnvelope()->getRecipients()[0]->getAddress())->toBe('ada@example.com')
            ->and($email->toString())->toContain('http://acme.test/reset-password/')
            ->and(url('/'))->toBe(config('app.url'));
    });

    test('a bare domain name is a subdomain of the central domain', function () {
        Event::fake([TenantCreated::class]);

        $this->post(route('central.admin.tenants.store'), tenantInput(['domain' => 'acme']))->assertSessionHasNoErrors();

        expect(Tenant::query()->sole()->domains()->value('domain'))->toBe('acme.'.config('tenancy.identification.central_domains')[0]);
    });

    test('the create page offers every industry with what it suggests', function () {
        $this->get(route('central.admin.tenants.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('industries', count(config('industries.profiles')))
                ->where('industries.0.value', 'hospitality')
                ->where('industries.0.suggested_modules', ['bookings', 'catalogue', 'cms', 'crm'])
            );
    });

    test('a tenant is created in the chosen industry', function () {
        Event::fake([TenantCreated::class]);

        $this->post(route('central.admin.tenants.store'), tenantInput(['industry' => 'beauty']))->assertSessionHasNoErrors();

        expect(Tenant::query()->sole()->industryProfile()->key)->toBe('beauty');
    });

    test('a tenant with no industry chosen is in the general one', function () {
        Event::fake([TenantCreated::class]);

        $this->post(route('central.admin.tenants.store'), tenantInput())->assertSessionHasNoErrors();

        expect(Tenant::query()->sole()->industry)->toBe('other');
    });

    test('a tenant can be created without an admin', function () {
        Event::fake([TenantCreated::class]);

        $this->post(route('central.admin.tenants.store'), tenantInput(['modules' => []]))
            ->assertSessionHas('success', __('tenants.flash.created', ['name' => 'acme']));

        expect(Tenant::query()->sole()->modules->all())->toBe([]);
    });

    test('the tenant must be valid', function (array $input, array $errors) {
        Event::fake([TenantCreated::class]);
        $taken = Tenant::query()->create(['name' => 'taken']);
        $taken->domains()->create(['domain' => 'taken.test']);

        $this->post(route('central.admin.tenants.store'), tenantInput($input))
            ->assertSessionHasErrors($errors);
    })->with([
        'missing name and domain' => [['name' => '', 'domain' => ''], ['name', 'domain']],
        'name format' => [['name' => 'Acme Ltd'], ['name']],
        'name taken' => [['name' => 'taken'], ['name']],
        'domain with a scheme' => [['domain' => 'https://acme.test/'], ['domain']],
        'domain taken' => [['domain' => 'taken.test'], ['domain']],
        'central domain' => [['domain' => 'localhost'], ['domain']],
        'unknown module' => [['modules' => ['blog']], ['modules.0']],
        'unknown industry' => [['industry' => 'piracy'], ['industry']],
        'admin without a name' => [['admin_email' => 'ada@example.com'], ['admin_first_name', 'admin_last_name']],
        'admin email' => [['admin_email' => 'not-an-email', 'admin_first_name' => 'Ada', 'admin_last_name' => 'L'], ['admin_email']],
    ]);

    test('format errors explain what\'s expected', function () {
        $this->post(route('central.admin.tenants.store'), tenantInput(['name' => 'Acme Ltd', 'domain' => 'https://acme.test/']))
            ->assertSessionHasErrors([
                'name' => __('tenants.validation.name_format'),
                'domain' => __('tenants.validation.domain_format'),
            ]);
    });

    test('a failed provision is shown on the form', function () {
        $this->mock(TenantProvisioner::class)
            ->shouldReceive('provision')
            ->andThrow(new RuntimeException('The database server refused the connection.'));

        $this->from(route('central.admin.tenants.create'))
            ->post(route('central.admin.tenants.store'), tenantInput())
            ->assertRedirect(route('central.admin.tenants.create'))
            ->assertSessionHas('error', __('tenants.flash.create_failed', ['message' => 'The database server refused the connection.']))
            ->assertSessionHasInput('name', 'acme');
    });

});

describe('setting up', function () {
    test('a failed setup removes the half-made database and keeps why, and the admin to invite', function () {
        $admin = ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com'];
        $tenant = Tenant::factory()->settingUp($admin)->create(['name' => 'acme']);

        // The database is made, then migrating it fails
        $this->partialMock(TenantDatabase::class)
            ->shouldReceive('create')
            ->andReturnUsing(function (Tenant $tenant) {
                app()->call([new CreateDatabase($tenant), 'handle']);

                throw new RuntimeException('Migrating failed.');
            });

        expect(fn () => ProvisionTenant::dispatch($tenant))->toThrow(RuntimeException::class, 'Migrating failed.');

        $tenant->refresh();

        expect($tenant->setup_status)->toBe(TenantSetupStatus::Failed)
            ->and($tenant->setup_error)->toBe('Migrating failed.')
            ->and($tenant->setup_admin)->toEqual($admin)
            ->and(app(TenantDatabase::class)->exists($tenant))->toBeFalse();

        $this->get(route('central.admin.tenants.show', $tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.data.setup_status.value', 'failed')
                ->where('tenant.data.setup_error', 'Migrating failed.')
            );
    });

    test('a failed setup can be tried again', function () {
        Queue::fake();
        $tenant = Tenant::factory()->setupFailed()->create();

        $this->post(route('central.admin.tenants.retry-setup', $tenant))
            ->assertSessionHas('success', __('tenants.flash.retrying', ['name' => $tenant->name]));

        expect($tenant->refresh()->setup_status)->toBe(TenantSetupStatus::SettingUp)
            ->and($tenant->setup_error)->toBeNull();

        Queue::assertPushed(ProvisionTenant::class, fn (ProvisionTenant $job) => $job->tenant->is($tenant));
    });

    test('only a failed setup can be tried again', function () {
        Queue::fake();
        $tenant = Tenant::factory()->settingUp()->create();

        $this->post(route('central.admin.tenants.retry-setup', $tenant))
            ->assertSessionHas('error', __('tenants.flash.retry_not_failed', ['name' => $tenant->name]));

        Queue::assertNothingPushed();
    });

    test('a tenant whose setup failed can be deleted without disabling it first', function () {
        $tenant = Tenant::factory()->setupFailed()->create(['name' => 'acme']);

        $this->delete(route('central.admin.tenants.destroy', $tenant), ['confirm_name' => 'acme'])
            ->assertRedirect(route('central.admin.tenants.index'));

        expect(Tenant::query()->count())->toBe(0);
    });

    test('tasks run across every tenant skip those that aren\'t set up', function () {
        $ready = Tenant::factory()->create();
        Tenant::factory()->settingUp()->create();
        Tenant::factory()->setupFailed()->create();

        expect(Tenant::query()->ready()->pluck('id')->all())->toBe([$ready->id]);

        $this->artisan('permissions:sync')->assertSuccessful();
    });
});

describe('editing', function () {
    test('the name and modules can be changed', function () {
        Event::fake([TenantCreated::class]);
        $tenant = Tenant::query()->create(['name' => 'acme', 'modules' => [TenantModule::Cms]]);

        $this->get(route('central.admin.tenants.edit', $tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/tenant/Edit')
                ->where('tenant.data.name', 'acme')
            );

        $this->put(route('central.admin.tenants.update', $tenant), ['name' => 'acme-ltd', 'modules' => ['tracker', 'cms']])
            ->assertRedirect(route('central.admin.tenants.show', $tenant))
            ->assertSessionHas('success');

        $tenant->refresh();

        expect($tenant->name)->toBe('acme-ltd')
            ->and($tenant->modules->all())->toBe([TenantModule::Cms, TenantModule::Tracker]);
    });

    test('the industry can be changed, leaving the modules as they are and refreshing its API', function () {
        $tenant = Tenant::query()->create(['name' => 'acme', 'modules' => [TenantModule::Tracker]]);
        $version = $tenant->run(fn () => app(ContentVersion::class)->current());

        $this->get(route('central.admin.tenants.edit', $tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.data.industry.value', 'other')
                ->has('industries', count(config('industries.profiles')))
            );

        $this->put(route('central.admin.tenants.update', $tenant), [
            'name' => 'acme',
            'industry' => 'health',
            'modules' => ['tracker'],
        ])->assertSessionHasNoErrors();

        $tenant->refresh();

        expect($tenant->industry)->toBe('health')
            ->and($tenant->modules->all())->toBe([TenantModule::Tracker])
            ->and($tenant->run(fn () => app(ContentVersion::class)->current()))->not->toBe($version);

        $this->get(route('central.admin.tenants.show', $tenant))
            ->assertInertia(fn (Assert $page) => $page->where('tenant.data.industry.label', __('industries.names.health')));
    });

    test('a platform fee can be set per tenant, in pounds, and cleared', function () {
        Event::fake([TenantCreated::class]);
        $tenant = Tenant::query()->create(['name' => 'acme']);

        $this->put(route('central.admin.tenants.update', $tenant), ['name' => 'acme', 'platform_fee_percent' => '60'])
            ->assertSessionHasErrors('platform_fee_percent');
        $this->put(route('central.admin.tenants.update', $tenant), ['name' => 'acme', 'platform_fee_percent' => '2.5', 'platform_fee_fixed' => '0.20'])
            ->assertSessionHasNoErrors();

        expect($tenant->fresh()->platformFeeFor(1000))->toBe(45);

        $this->get(route('central.admin.tenants.edit', $tenant))
            ->assertInertia(fn (Assert $page) => $page->where('tenant.data.platform_fee_percent', '2.50')->where('tenant.data.platform_fee_fixed', '0.20'));

        $this->put(route('central.admin.tenants.update', $tenant), ['name' => 'acme', 'platform_fee_percent' => '', 'platform_fee_fixed' => ''])
            ->assertSessionHasNoErrors();

        expect($tenant->fresh()->platformFeeFor(1000))->toBe(0);
    });

    test('a tenant keeps its own name, but can\'t take another\'s', function () {
        Event::fake([TenantCreated::class]);
        $tenant = Tenant::query()->create(['name' => 'acme']);
        Tenant::query()->create(['name' => 'globex']);

        $this->put(route('central.admin.tenants.update', $tenant), ['name' => 'acme'])->assertSessionHasNoErrors();
        $this->put(route('central.admin.tenants.update', $tenant), ['name' => 'globex'])->assertSessionHasErrors('name');
    });
});

describe('disabling and deleting', function () {
    beforeEach(function () {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
        $this->tenant = Tenant::query()->create(['name' => 'acme']);
    });

    test('a tenant can be disabled and enabled again', function () {
        $this->post(route('central.admin.tenants.disable', $this->tenant))->assertSessionHas('success');

        expect($this->tenant->refresh()->isDisabled())->toBeTrue();

        $this->get(route('central.admin.tenants.show', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page->where('tenant.data.is_disabled', true));

        $this->post(route('central.admin.tenants.enable', $this->tenant))->assertSessionHas('success');

        expect($this->tenant->refresh()->isDisabled())->toBeFalse();
    });

    test('only a disabled tenant can be deleted', function () {
        $this->delete(route('central.admin.tenants.destroy', $this->tenant), ['confirm_name' => 'acme'])
            ->assertSessionHasErrors(['tenant' => __('tenants.validation.disable_first')]);

        expect(Tenant::query()->count())->toBe(1);
    });

    test('deleting needs the tenant\'s name typed', function () {
        $this->tenant->update(['disabled_at' => now()]);

        $this->delete(route('central.admin.tenants.destroy', $this->tenant), ['confirm_name' => 'acm'])
            ->assertSessionHasErrors(['confirm_name' => __('tenants.validation.confirm_name')]);

        expect(Tenant::query()->count())->toBe(1);
    });

    test('a disabled tenant is deleted along with its database', function () {
        $this->tenant->update(['disabled_at' => now()]);
        $this->tenant->domains()->create(['domain' => 'acme.test']);

        $this->delete(route('central.admin.tenants.destroy', $this->tenant), ['confirm_name' => 'acme'])
            ->assertRedirect(route('central.admin.tenants.index'))
            ->assertSessionHas('success', __('tenants.flash.deleted', ['name' => 'acme']));

        expect(Tenant::query()->count())->toBe(0);
        // Deleting the database is the tenancy pipeline's job, listening for this
        Event::assertDispatched(TenantDeleted::class);
    });
});
