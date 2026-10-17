<?php

use App\Enums\TenantModule;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Authorisation\Enums\Role;
use App\Services\Central\TenantProvisioner;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;

beforeEach(function () {
    $this->withoutVite();
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

    test('a tenant is provisioned with its database, domain, modules and first admin, who is invited on the tenant\'s domain', function () {
        $response = $this->post(route('central.admin.tenants.store'), tenantInput([
            'domain' => ' Acme.TEST ',
            'admin_first_name' => 'Ada',
            'admin_last_name' => 'Lovelace',
            'admin_email' => 'ada@example.com',
        ]));

        $tenant = Tenant::query()->with('domains')->sole();

        $response->assertRedirect(route('central.admin.tenants.show', $tenant))
            ->assertSessionHas('success', __('tenants.flash.created_and_invited', ['name' => 'acme', 'email' => 'ada@example.com']));

        expect($tenant->name)->toBe('acme')
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

    test('a failed provision removes the half-made tenant', function () {
        Event::fake([TenantCreated::class, TenantDeleted::class]);
        Tenant::created(fn () => throw new RuntimeException('Migrating failed.'));

        expect(fn () => app(TenantProvisioner::class)->provision('acme', 'acme.test', collect()))
            ->toThrow(RuntimeException::class, 'Migrating failed.');

        expect(Tenant::query()->count())->toBe(0);
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
