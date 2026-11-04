<?php

use App\Enums\BookingType;
use App\Enums\TenantModule;

/*
|--------------------------------------------------------------------------
| Industries
|--------------------------------------------------------------------------
|
| The kinds of business a tenant can be (#173), and what each starts with. A tenant can change
| all of it. To add an industry, add it to `profiles` here, then give it a name and description
| in lang/en/industries.php, and any words of its own under `terminology` there. No other code
| needs to change.
|
*/

return [
    // Tenants without an industry, or whose industry is no longer listed, use this one
    'fallback' => 'other',

    'profiles' => [
        'hospitality' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::Table, BookingType::Ticket, BookingType::Enquiry],
            'customer_fields' => ['allergies', 'dietary_needs', 'seating_preference'],
        ],
        'beauty' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::Appointment, BookingType::Enquiry],
            'customer_fields' => ['colour_formula', 'patch_test_date', 'allergies'],
        ],
        'health' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::Appointment, BookingType::GroupClass],
            'customer_fields' => ['medical_history', 'injuries', 'allergies', 'emergency_contact'],
        ],
        'fitness' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::GroupClass, BookingType::Appointment, BookingType::Hire],
            'customer_fields' => ['injuries', 'fitness_goals', 'emergency_contact'],
        ],
        'events' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm, TenantModule::Dj],
            'booking_types' => [BookingType::Enquiry, BookingType::Ticket, BookingType::Hire],
            'customer_fields' => ['event_type', 'event_date', 'venue'],
        ],
        'trades' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::Visit, BookingType::Enquiry],
            'customer_fields' => ['property_address', 'access_notes', 'vehicle_registration'],
        ],
        'pets' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::Appointment, BookingType::Visit, BookingType::GroupClass],
            'customer_fields' => ['pets', 'breed', 'vet_details', 'allergies'],
        ],
        'education' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::GroupClass, BookingType::Appointment],
            'customer_fields' => ['school_year', 'learning_needs', 'emergency_contact'],
        ],
        'rentals' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::Hire, BookingType::Enquiry],
            'customer_fields' => ['company_name', 'access_notes'],
        ],
        'professional' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::Appointment, BookingType::Enquiry],
            'customer_fields' => ['company_name', 'referred_by'],
        ],
        'retail' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::Enquiry, BookingType::Appointment],
            'customer_fields' => ['delivery_address'],
        ],
        'other' => [
            'suggested_modules' => [TenantModule::Cms, TenantModule::Crm],
            'booking_types' => [BookingType::Appointment, BookingType::Enquiry],
            'customer_fields' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer Fields
    |--------------------------------------------------------------------------
    |
    | The fields a tenant can choose from, with labels in lang/en/industries.php. Sensitive ones
    | are restricted by permission and kept out of exports once customer profiles use them (#181).
    |
    */
    'customer_fields' => [
        'access_notes' => ['sensitive' => false],
        'allergies' => ['sensitive' => true],
        'breed' => ['sensitive' => false],
        'colour_formula' => ['sensitive' => false],
        'company_name' => ['sensitive' => false],
        'delivery_address' => ['sensitive' => false],
        'dietary_needs' => ['sensitive' => true],
        'emergency_contact' => ['sensitive' => true],
        'event_date' => ['sensitive' => false],
        'event_type' => ['sensitive' => false],
        'fitness_goals' => ['sensitive' => false],
        'injuries' => ['sensitive' => true],
        'learning_needs' => ['sensitive' => true],
        'medical_history' => ['sensitive' => true],
        'patch_test_date' => ['sensitive' => false],
        'pets' => ['sensitive' => false],
        'property_address' => ['sensitive' => false],
        'referred_by' => ['sensitive' => false],
        'school_year' => ['sensitive' => false],
        'seating_preference' => ['sensitive' => false],
        'vehicle_registration' => ['sensitive' => false],
        'venue' => ['sensitive' => false],
        'vet_details' => ['sensitive' => false],
    ],
];
