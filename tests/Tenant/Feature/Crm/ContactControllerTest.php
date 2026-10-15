<?php

use App\Enums\TenantModule;
use App\Models\Crm\Address;
use App\Models\Crm\Contact;
use App\Services\Authorisation\Enums\CrmPermission;
use Inertia\Testing\AssertableInertia as Assert;

function validContactData(array $overrides = []): array
{
    return [
        'title' => 'Dr',
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane@example.test',
        'telephone' => '07700 900000',
        'is_spam' => false,
        'marketing_email' => true,
        'marketing_sms' => false,
        'marketing_telephone' => false,
        'addresses' => [
            [
                'postcode' => 'M1 1AA',
                'line_1' => '1 High Street',
                'line_2' => null,
                'line_3' => null,
                'town_city' => 'Manchester',
                'county' => null,
                'country' => 'United Kingdom',
            ],
        ],
        ...$overrides,
    ];
}

function blankAddress(): array
{
    return array_fill_keys(Address::FIELDS, '');
}

describe('access', function () {
    test('guests are redirected to the login page', function () {
        $this->get(route('admin.crm.contacts.index'))->assertRedirect(route('login'));
    });

    test('users without the view permission cannot see the index', function () {
        $this->actingAs($this->createUserWithPermissions([]));

        $this->get(route('admin.crm.contacts.index'))->assertForbidden();
    });

    test('users with only the view permission can see contacts but not change them', function () {
        $this->actingAs($this->createUserWithPermissions([CrmPermission::ViewContacts]));
        $contact = Contact::factory()->create();

        $this->get(route('admin.crm.contacts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/crm/contact/Index')
                ->has('contacts.data', 1)
                ->where('canCreate', false)
            );

        $this->get(route('admin.crm.contacts.show', $contact))->assertOk();
        $this->post(route('admin.crm.contacts.store'), validContactData())->assertForbidden();
        $this->post(route('admin.crm.contacts.spam.store', $contact))->assertForbidden();
        $this->delete(route('admin.crm.contacts.destroy', $contact))->assertForbidden();
    });

    test('the pages return not found when the tenant does not have the CRM module', function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
        tenant()->update(['modules' => [TenantModule::Tracker]]);

        $this->get(route('admin.crm.contacts.index'))->assertNotFound();
    });
});

describe('index', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('it can be searched by name, email or telephone', function () {
        Contact::factory()->create(['first_name' => 'Jane', 'email' => 'jane@example.test', 'telephone' => '07700 111111']);
        Contact::factory()->create(['first_name' => 'Bob', 'email' => 'bob@example.test', 'telephone' => '07700 222222']);

        foreach (['Jane', 'jane@', '111111'] as $term) {
            $this->get(route('admin.crm.contacts.index', ['filter[search]' => $term]))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('contacts.data', 1)
                    ->where('contacts.data.0.first_name', 'Jane')
                );
        }
    });

    test('it can be filtered by spam', function () {
        Contact::factory()->create();
        Contact::factory()->spam()->create();

        $this->get(route('admin.crm.contacts.index', ['filter[is_spam]' => '1']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.is_spam', true)
            );

        $this->get(route('admin.crm.contacts.index', ['filter[is_spam]' => '0']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('contacts.data', 1)
                ->where('contacts.data.0.is_spam', false)
            );
    });

    test('deleted contacts are hidden unless asked for, and can then only be restored', function () {
        Contact::factory()->create();
        Contact::factory()->create()->delete();

        $this->get(route('admin.crm.contacts.index'))
            ->assertInertia(fn (Assert $page) => $page->has('contacts.data', 1));

        $this->get(route('admin.crm.contacts.index', ['filter[trashed]' => 'only']))
            ->assertInertia(function (Assert $page) {
                $page->has('contacts.data', 1);

                $labels = collect($page->toArray()['props']['contacts']['data'][0]['actions'])->pluck('label');

                expect($labels->all())->toBe([__('crm.contacts.actions.restore')]);
            });
    });
});

describe('store', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('it creates a contact with its addresses, ignoring blank address rows', function () {
        $data = validContactData();
        $data['addresses'][] = blankAddress();

        $this->post(route('admin.crm.contacts.store'), $data)
            ->assertRedirect(route('admin.crm.contacts.index'));

        $contact = Contact::query()->sole();

        expect($contact->name_with_title)->toBe('Dr Jane Smith')
            ->and($contact->marketing_email)->toBeTrue()
            ->and($contact->addresses)->toHaveCount(1)
            ->and($contact->addresses->first()->postcode)->toBe('M1 1AA');
    });

    test('the email must be unique, including against deleted contacts', function () {
        Contact::factory()->create(['email' => 'jane@example.test'])->delete();

        $this->post(route('admin.crm.contacts.store'), validContactData())
            ->assertSessionHasErrors(['email' => __('crm.contacts.validation.email_taken')]);
    });

    test('names and a valid email are required', function () {
        $this->post(route('admin.crm.contacts.store'), validContactData(['first_name' => '', 'last_name' => '', 'email' => 'nope']))
            ->assertSessionHasErrors(['first_name', 'last_name', 'email']);
    });

    test('each address needs a postcode and first line, and cannot be added twice', function () {
        $partial = [...blankAddress(), 'town_city' => 'Leeds'];

        $this->post(route('admin.crm.contacts.store'), validContactData(['addresses' => [$partial]]))
            ->assertSessionHasErrors(['addresses.0.postcode', 'addresses.0.line_1']);

        $address = validContactData()['addresses'][0];

        $this->post(route('admin.crm.contacts.store'), validContactData(['addresses' => [$address, [...$address, 'postcode' => 'm1 1aa']]]))
            ->assertSessionHasErrors(['addresses' => __('crm.contacts.validation.duplicate_address')]);
    });
});

describe('update', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('it updates the contact and replaces its addresses', function () {
        $contact = Contact::factory()->create(['email' => 'jane@example.test']);
        Address::factory()->for($contact, 'addressable')->count(2)->create();

        $this->put(route('admin.crm.contacts.update', $contact), validContactData(['first_name' => 'Janet']))
            ->assertRedirect(route('admin.crm.contacts.index'));

        $contact->refresh();

        expect($contact->first_name)->toBe('Janet')
            ->and($contact->addresses)->toHaveCount(1)
            ->and($contact->addresses->first()->postcode)->toBe('M1 1AA');
    });

    test('a contact can keep its own email', function () {
        $contact = Contact::factory()->create(['email' => 'jane@example.test']);

        $this->put(route('admin.crm.contacts.update', $contact), validContactData())
            ->assertSessionHasNoErrors();
    });
});

describe('spam', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('a contact can be marked and unmarked as spam', function () {
        $contact = Contact::factory()->create();

        $this->post(route('admin.crm.contacts.spam.store', $contact))->assertRedirect();
        expect($contact->fresh()->is_spam)->toBeTrue();

        $this->delete(route('admin.crm.contacts.spam.destroy', $contact))->assertRedirect();
        expect($contact->fresh()->is_spam)->toBeFalse();
    });
});

describe('delete and restore', function () {
    beforeEach(function () {
        $this->actingAs($this->createUserWithPermissions(CrmPermission::cases()));
    });

    test('deleting a contact soft deletes it and keeps its addresses', function () {
        $contact = Contact::factory()->create();
        Address::factory()->for($contact, 'addressable')->create();

        $this->delete(route('admin.crm.contacts.destroy', $contact))->assertRedirect();

        expect($contact->fresh()->trashed())->toBeTrue()
            ->and(Address::query()->count())->toBe(1);
    });

    test('a deleted contact can be restored', function () {
        $contact = Contact::factory()->create();
        $contact->delete();

        $this->patch(route('admin.crm.contacts.restore', $contact))->assertRedirect();

        expect($contact->fresh()->trashed())->toBeFalse();
    });
});
