<?php

namespace Database\Seeders;

use App\Data\Crm\FormFieldSettings;
use App\Enums\Crm\FormFieldType;
use App\Enums\Crm\LocationType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\Crm\SocialMedia;
use App\Models\Crm\Address;
use App\Models\Crm\Contact;
use App\Models\Crm\Form;
use App\Models\Crm\FormSubmission;
use App\Models\Crm\OpeningHourException;
use App\Models\Crm\OrganisationUnit;
use Illuminate\Database\Seeder;

/**
 * Seeds example CRM data: a company with one location of each type and their opening hours, two
 * of them with a couple of departments, a handful of contacts (one marked as spam), an example contact form and some submissions of it.
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=CrmSeeder`.
 * Safe to re-run: each kind of data is only seeded when none exists yet.
 */
class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedOrganisationUnits();
        $this->seedLocations();
        $this->seedContacts();
        $this->seedForms();
        $this->seedFormSubmissions();
    }

    /**
     * One location of each type, so every kind of hours has something to show: split shifts with a
     * closure, a holiday and special hours coming up, a mobile business's areas, always open online,
     * and by appointment. Dates are relative to now, and a location that already has hours is left
     * alone.
     */
    protected function seedLocations(): void
    {
        $company = OrganisationUnit::query()->where('type', OrganisationUnitType::Company)->first();

        if ($company === null) {
            return;
        }

        $online = OrganisationUnit::query()->firstWhere('slug', 'online-support')
            ?? OrganisationUnit::factory()->online($company)->create(['name' => 'Online Support', 'slug' => 'online-support']);

        if (! OrganisationUnit::query()->where('slug', 'harrogate-studio')->exists()) {
            $studio = OrganisationUnit::factory()->byAppointment($company)->create(['name' => 'Harrogate Studio', 'slug' => 'harrogate-studio']);
            Address::factory()->for($studio, 'addressable')->create(['town_city' => 'Harrogate']);
        }

        $this->seedHours($online, array_map(fn (int $day) => [$day, '00:00', '00:00', null], range(1, 7)));

        $manchester = OrganisationUnit::query()->firstWhere('slug', 'manchester-office');

        if ($manchester !== null && $manchester->openingHours()->doesntExist()) {
            $manchester->update(['timezone' => 'Europe/London']);

            $this->seedHours($manchester, [
                ...array_merge(...array_map(fn (int $day) => [
                    [$day, '09:00', '12:30', 'Morning'],
                    [$day, '13:30', '17:30', 'Afternoon'],
                ], range(1, 5))),
                [6, '10:00', '14:00', null],
            ]);

            $today = now()->startOfDay();

            OpeningHourException::factory()->for($manchester, 'organisationUnit')->create([
                'name' => 'Staff training day',
                'starts_on' => $today->copy()->addWeeks(2),
                'ends_on' => $today->copy()->addWeeks(2),
            ]);
            OpeningHourException::factory()->for($manchester, 'organisationUnit')->specialHours([
                ['opens_at' => '10:00', 'closes_at' => '20:00', 'label' => 'Late opening'],
            ])->create([
                'name' => 'Open evening',
                'starts_on' => $today->copy()->addWeeks(3),
                'ends_on' => $today->copy()->addWeeks(3),
            ]);
            OpeningHourException::factory()->for($manchester, 'organisationUnit')->create([
                'name' => 'Summer holiday',
                'starts_on' => $today->copy()->addMonths(2),
                'ends_on' => $today->copy()->addMonths(2)->addWeek(),
            ]);
        }

        $leeds = OrganisationUnit::query()->firstWhere('slug', 'leeds-office');

        if ($leeds !== null && $leeds->openingHours()->doesntExist()) {
            $leeds->update([
                'location_type' => LocationType::Mobile,
                'timezone' => 'Europe/London',
                'service_areas' => ['LS1', 'LS2', 'LS6', 'BD1'],
            ]);

            $this->seedHours($leeds, array_map(fn (int $day) => [$day, '08:00', '18:00', null], range(1, 6)));
        }
    }

    /**
     * @param  list<array{0: int, 1: string, 2: string, 3: string|null}>  $periods
     */
    protected function seedHours(OrganisationUnit $location, array $periods): void
    {
        if ($location->openingHours()->exists()) {
            return;
        }

        foreach ($periods as [$day, $opens, $closes, $label]) {
            $location->openingHours()->create([
                'day_of_week' => $day,
                'opens_at' => $opens,
                'closes_at' => $closes,
                'label' => $label,
            ]);
        }
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
