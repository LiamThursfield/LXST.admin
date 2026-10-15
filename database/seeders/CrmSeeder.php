<?php

namespace Database\Seeders;

use App\Data\Crm\FormFieldSettings;
use App\Enums\Crm\FormFieldType;
use App\Enums\Crm\SocialMedia;
use App\Models\Crm\Address;
use App\Models\Crm\Contact;
use App\Models\Crm\Form;
use App\Models\Crm\FormSubmission;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Database\Seeder;

/**
 * Seeds example CRM data: a company with two locations, each with a couple of departments,
 * a handful of contacts (one marked as spam), an example contact form and some submissions of it.
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=CrmSeeder`.
 * Safe to re-run: each kind of data is only seeded when none exists yet.
 */
class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedOrganisationUnits();
        $this->seedContacts();
        $this->seedForms();
        $this->seedFormSubmissions();
    }

    protected function seedFormSubmissions(): void
    {
        $form = Form::query()->with('fields')->first();

        if ($form === null || FormSubmission::query()->exists()) {
            return;
        }

        // A few genuine contacts plus the spam one, so both kinds of submission exist
        $contacts = Contact::query()->where('is_spam', false)->take(4)->get()
            ->push(Contact::query()->where('is_spam', true)->first())
            ->filter();

        $contacts->each(
            fn (Contact $contact) => FormSubmission::factory()
                ->answering($form, $contact)
                ->state(['is_spam' => $contact->is_spam])
                ->create()
        );
    }

    protected function seedForms(): void
    {
        if (Form::query()->exists()) {
            return;
        }

        $form = Form::factory()->withMarketing()->create([
            'name' => 'Contact us',
            'submit_button_text' => 'Send message',
            'success_message' => 'Thanks for getting in touch, we will reply soon.',
        ]);

        $fields = [
            [FormFieldType::ContactFirstName, 'First name', 'first_name', true, null],
            [FormFieldType::ContactLastName, 'Last name', 'last_name', true, null],
            [FormFieldType::ContactEmail, 'Email', 'email', true, null],
            [FormFieldType::ContactTelephone, 'Phone', 'phone', false, null],
            [FormFieldType::Textarea, 'Message', 'message', true, new FormFieldSettings(max_length: 2000, rows: 5)],
        ];

        foreach ($fields as $order => [$type, $name, $slug, $required, $settings]) {
            $form->fields()->create([
                'type' => $type,
                'name' => $name,
                'slug' => $slug,
                'is_required' => $required,
                'order' => $order,
                'settings' => $settings,
            ]);
        }
    }

    protected function seedContacts(): void
    {
        if (Contact::query()->exists()) {
            return;
        }

        Contact::factory()->count(8)->create()->each(
            fn (Contact $contact) => Address::factory()->for($contact, 'addressable')->create()
        );
        Contact::factory()->count(3)->optedIn()->create();
        Contact::factory()->spam()->create();
    }

    protected function seedOrganisationUnits(): void
    {
        if (OrganisationUnit::query()->exists()) {
            return;
        }

        $company = OrganisationUnit::factory()->company()->create([
            'name' => 'Acme Ltd',
            'socials' => [
                SocialMedia::LinkedIn->value => 'https://www.linkedin.com/company/acme',
                SocialMedia::Instagram->value => 'https://www.instagram.com/acme',
            ],
        ]);
        Address::factory()->for($company, 'addressable')->create();

        foreach (['Manchester', 'Leeds'] as $city) {
            $location = OrganisationUnit::factory()->location($company)->create(['name' => "{$city} Office"]);
            Address::factory()->for($location, 'addressable')->create(['town_city' => $city]);

            foreach (['Sales', 'Support'] as $department) {
                OrganisationUnit::factory()->department($location)->create(['name' => "{$city} {$department}"]);
            }
        }
    }
}
