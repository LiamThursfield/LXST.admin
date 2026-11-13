<?php

/*
|--------------------------------------------------------------------------
| Industries Language Lines
|--------------------------------------------------------------------------
|
| The kinds of business a tenant can be, and the words each one uses. See config/industries.php
| for each industry's defaults.
|
| Any string in lang/en can use a term as a placeholder: :customer and :customers, or :Customer
| and :Customers to capitalise. Vue's trans() fills them in by itself; in PHP, use
| Terminology::translate(). A term can start with a vowel, so avoid "a" and "an" before one:
| write "New :booking", not "Add a :booking".
|
| A term here is written as it reads mid-sentence, so in lower case unless it's a name.
|
*/

return [
    'names' => [
        'hospitality' => 'Hospitality',
        'beauty' => 'Beauty and wellbeing',
        'health' => 'Health',
        'fitness' => 'Fitness and activities',
        'events' => 'Events and entertainment',
        'trades' => 'Trades and home services',
        'pets' => 'Pets',
        'education' => 'Education',
        'rentals' => 'Rentals and spaces',
        'professional' => 'Professional services',
        'retail' => 'Retail',
        'other' => 'Other',
    ],

    'descriptions' => [
        'hospitality' => 'Restaurants, cafés, bars, takeaways, bakeries and food trucks',
        'beauty' => 'Barbers, hair and nail salons, lashes and brows, spas, massage, tattoo and piercing',
        'health' => 'Physios, chiropractors, dentists, clinics, therapists and opticians',
        'fitness' => 'Gyms, studios, personal trainers, climbing, golf, courts and pitches, escape rooms',
        'events' => 'DJs and bands, photographers, venues, caterers, planners and party hire',
        'trades' => 'Plumbers, electricians, cleaners, gardeners, garages and mobile valeting',
        'pets' => 'Groomers, dog walkers, trainers, kennels and vets',
        'education' => 'Tutors, music and driving lessons, courses and workshops',
        'rentals' => 'Meeting rooms, coworking, studios, equipment and holiday lets',
        'professional' => 'Consultants, accountants, legal and coaching',
        'retail' => 'Shops, florists and makers',
        'other' => 'Any other business, using general words',
    ],

    // What each term refers to, for the page where a tenant changes them
    'term_names' => [
        'customer' => 'Customers',
        'booking' => 'Bookings',
        'staff_member' => 'Staff',
        'service' => 'Services',
        'resource' => 'Resources',
        'location' => 'Locations',
        'catalogue' => 'Catalogues',
    ],

    // The words used unless an industry or tenant has its own. Every term must be listed here.
    'terms' => [
        'customer' => ['singular' => 'customer', 'plural' => 'customers'],
        'booking' => ['singular' => 'booking', 'plural' => 'bookings'],
        'staff_member' => ['singular' => 'staff member', 'plural' => 'staff members'],
        'service' => ['singular' => 'service', 'plural' => 'services'],
        'resource' => ['singular' => 'resource', 'plural' => 'resources'],
        'location' => ['singular' => 'location', 'plural' => 'locations'],
        'catalogue' => ['singular' => 'catalogue', 'plural' => 'catalogues'],
    ],

    // Each industry's own words. Anything left out comes from `terms`.
    'terminology' => [
        'hospitality' => [
            'customer' => ['singular' => 'guest', 'plural' => 'guests'],
            'booking' => ['singular' => 'reservation', 'plural' => 'reservations'],
            'staff_member' => ['singular' => 'team member', 'plural' => 'team members'],
            'resource' => ['singular' => 'table', 'plural' => 'tables'],
            'location' => ['singular' => 'venue', 'plural' => 'venues'],
            'catalogue' => ['singular' => 'menu', 'plural' => 'menus'],
        ],
        'beauty' => [
            'customer' => ['singular' => 'client', 'plural' => 'clients'],
            'booking' => ['singular' => 'appointment', 'plural' => 'appointments'],
            'staff_member' => ['singular' => 'stylist', 'plural' => 'stylists'],
            'service' => ['singular' => 'treatment', 'plural' => 'treatments'],
            'resource' => ['singular' => 'chair', 'plural' => 'chairs'],
            'location' => ['singular' => 'salon', 'plural' => 'salons'],
            'catalogue' => ['singular' => 'price list', 'plural' => 'price lists'],
        ],
        'health' => [
            'customer' => ['singular' => 'patient', 'plural' => 'patients'],
            'booking' => ['singular' => 'appointment', 'plural' => 'appointments'],
            'staff_member' => ['singular' => 'practitioner', 'plural' => 'practitioners'],
            'service' => ['singular' => 'treatment', 'plural' => 'treatments'],
            'resource' => ['singular' => 'room', 'plural' => 'rooms'],
            'location' => ['singular' => 'clinic', 'plural' => 'clinics'],
            'catalogue' => ['singular' => 'price list', 'plural' => 'price lists'],
        ],
        'fitness' => [
            'customer' => ['singular' => 'member', 'plural' => 'members'],
            'booking' => ['singular' => 'session', 'plural' => 'sessions'],
            'staff_member' => ['singular' => 'coach', 'plural' => 'coaches'],
            'service' => ['singular' => 'class', 'plural' => 'classes'],
            'resource' => ['singular' => 'space', 'plural' => 'spaces'],
            'location' => ['singular' => 'site', 'plural' => 'sites'],
        ],
        'events' => [
            'customer' => ['singular' => 'client', 'plural' => 'clients'],
            'staff_member' => ['singular' => 'performer', 'plural' => 'performers'],
            'service' => ['singular' => 'package', 'plural' => 'packages'],
            'location' => ['singular' => 'venue', 'plural' => 'venues'],
        ],
        'trades' => [
            'booking' => ['singular' => 'job', 'plural' => 'jobs'],
            'staff_member' => ['singular' => 'technician', 'plural' => 'technicians'],
            'resource' => ['singular' => 'vehicle', 'plural' => 'vehicles'],
            'location' => ['singular' => 'service area', 'plural' => 'service areas'],
        ],
        'pets' => [
            'customer' => ['singular' => 'owner', 'plural' => 'owners'],
            'booking' => ['singular' => 'appointment', 'plural' => 'appointments'],
            'staff_member' => ['singular' => 'carer', 'plural' => 'carers'],
        ],
        'education' => [
            'customer' => ['singular' => 'student', 'plural' => 'students'],
            'booking' => ['singular' => 'lesson', 'plural' => 'lessons'],
            'staff_member' => ['singular' => 'tutor', 'plural' => 'tutors'],
            'service' => ['singular' => 'course', 'plural' => 'courses'],
            'resource' => ['singular' => 'room', 'plural' => 'rooms'],
        ],
        'rentals' => [
            'booking' => ['singular' => 'hire', 'plural' => 'hires'],
            'staff_member' => ['singular' => 'team member', 'plural' => 'team members'],
            'service' => ['singular' => 'rental', 'plural' => 'rentals'],
            'resource' => ['singular' => 'space', 'plural' => 'spaces'],
            'location' => ['singular' => 'site', 'plural' => 'sites'],
        ],
        'professional' => [
            'customer' => ['singular' => 'client', 'plural' => 'clients'],
            'booking' => ['singular' => 'appointment', 'plural' => 'appointments'],
            'staff_member' => ['singular' => 'consultant', 'plural' => 'consultants'],
            'resource' => ['singular' => 'room', 'plural' => 'rooms'],
            'location' => ['singular' => 'office', 'plural' => 'offices'],
        ],
        'retail' => [
            'booking' => ['singular' => 'order', 'plural' => 'orders'],
            'staff_member' => ['singular' => 'team member', 'plural' => 'team members'],
            'service' => ['singular' => 'product', 'plural' => 'products'],
            'location' => ['singular' => 'shop', 'plural' => 'shops'],
        ],
    ],

    'customer_fields' => [
        'access_notes' => 'Access notes',
        'allergies' => 'Allergies',
        'breed' => 'Breed',
        'colour_formula' => 'Colour formula',
        'company_name' => 'Company name',
        'delivery_address' => 'Delivery address',
        'dietary_needs' => 'Dietary needs',
        'emergency_contact' => 'Emergency contact',
        'event_date' => 'Event date',
        'event_type' => 'Event type',
        'fitness_goals' => 'Fitness goals',
        'injuries' => 'Injuries',
        'learning_needs' => 'Learning needs',
        'medical_history' => 'Medical history',
        'patch_test_date' => 'Patch test date',
        'pets' => 'Pets',
        'property_address' => 'Property address',
        'referred_by' => 'Referred by',
        'school_year' => 'School year',
        'seating_preference' => 'Seating preference',
        'vehicle_registration' => 'Vehicle registration',
        'venue' => 'Venue',
        'vet_details' => 'Vet details',
    ],
];
