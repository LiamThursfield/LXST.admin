<?php

/*
|--------------------------------------------------------------------------
| Marketing Language Lines
|--------------------------------------------------------------------------
|
| The public site on the central domain: the home page and a page for each module.
| Lists are plain arrays, read in Vue with translationList().
|
*/

return [
    'nav' => [
        'modules' => 'Modules',
        'industries' => 'Industries',
        'developers' => 'Developers',
        'sign_in' => 'Sign in',
        'dashboard' => 'Dashboard',
        'open_menu' => 'Open menu',
    ],

    'footer' => [
        'tagline' => 'The back office for small businesses.',
        'modules' => 'Modules',
        'product' => 'Product',
        'built_by' => 'Built by LXST.digital',
    ],

    'home' => [
        'head' => 'The back office for small businesses',
        'title' => 'The back office for small businesses',
        'description' => 'Bookings, the website, customers and menus, run from one admin. Each business switches on only the modules it needs, and the admin talks in its own words.',
        'see_modules' => 'See the modules',
        'sign_in' => 'Sign in',
        'go_to_dashboard' => 'Go to dashboard',

        'demo' => [
            'label' => 'See it as',
            'caption' => 'Same admin, different words. Pick an industry and the labels follow.',
            'examples' => [
                'beauty' => 'A salon',
                'hospitality' => 'A restaurant',
                'fitness' => 'A gym',
                'health' => 'A clinic',
                'trades' => 'A plumber',
                'pets' => 'A dog groomer',
            ],
        ],

        'modules' => [
            'eyebrow' => 'Modules',
            'title' => 'Six modules. Switch on what the business needs.',
            'description' => 'Every business gets its own admin on its own address. Modules can be switched on later, as the business grows, and they work together once they are.',
            'explore' => 'Explore :module',
        ],

        'booking_types' => [
            'eyebrow' => 'Bookings',
            'title' => 'One booking engine, seven ways to book',
            'description' => 'A barber and a bike hire shop take bookings very differently. Each service picks the way it works, and Bookings handles the rest.',
            'types' => [
                'appointment' => [
                    'title' => 'Appointments',
                    'description' => 'One customer, one staff member, a set length of time.',
                ],
                'class' => [
                    'title' => 'Classes',
                    'description' => 'A session with limited places that many people book into.',
                ],
                'table' => [
                    'title' => 'Tables',
                    'description' => 'Booked by party size, seated at the table that fits.',
                ],
                'hire' => [
                    'title' => 'Hire',
                    'description' => 'Rooms, kit or vehicles, by the hour or the day.',
                ],
                'visit' => [
                    'title' => 'Visits',
                    'description' => 'The business goes to the customer, with travel time allowed for.',
                ],
                'ticket' => [
                    'title' => 'Tickets',
                    'description' => 'A one-off event with a fixed number of tickets.',
                ],
                'enquiry' => [
                    'title' => 'Enquiries',
                    'description' => 'Anything that needs a chat or a quote before it is booked.',
                ],
            ],
        ],

        'developers' => [
            'eyebrow' => 'Developers',
            'title' => 'Works with the website a business already has',
            'description' => 'Pages, menus, forms, opening hours and price lists come out of a plain JSON API, so any site can show them. Forms post back in, with spam caught on the way.',
            'points' => [
                'The business is worked out from the address, so a site can start reading straight away',
                'Rate limited per business, so one busy site cannot slow another',
                'Page views counted in the admin, with or without Google Tag Manager',
            ],
        ],

        'industries' => [
            'eyebrow' => 'Industries',
            'title' => 'Made for all kinds of business',
            'description' => 'Each business picks its industry when it is set up. That decides the words the admin uses and the details it keeps about each customer, and both can be changed.',
        ],

        'closing' => [
            'title' => "Looking for your business's admin?",
            'description' => "This is the platform's own address. Each business signs in on its own site, so use the link you were sent, or ask whoever set your site up.",
            'sign_in' => 'Platform sign in',
        ],
    ],

    // The product mock-ups. Their sample data is in resources/js/lib/marketing.ts.
    'mock' => [
        'today' => 'Today',
        'dashboard' => 'Dashboard',
        'website' => 'Website',
        'paid_online' => 'Paid online',
        'new_this_week' => 'New this week',
        'status' => [
            'confirmed' => 'Confirmed',
            'deposit_paid' => 'Deposit paid',
            'checked_in' => 'Arrived',
            'requested' => 'Requested',
            'full' => 'Full',
        ],
        'pages' => 'Pages',
        'menus' => 'Menus',
        'redirects' => 'Redirects',
        'analytics' => 'Analytics',
        'published' => 'Published',
        'scheduled' => 'Scheduled',
        'draft' => 'Draft',
        'views' => 'Views, last 30 days',
        'contacts' => 'Contacts',
        'forms' => 'Forms',
        'locations' => 'Locations',
        'history' => 'History',
        'sensitive' => 'Sensitive',
        'catalogues' => 'Catalogues',
        'tags' => 'Tags',
        'sold_out' => 'Sold out',
        'goals' => 'Goals',
        'tasks' => 'Tasks',
        'this_week' => 'This week',
        'library' => 'Library',
        'gigs' => 'Gigs',
        'setlists' => 'Setlists',
        'now_playing' => 'Now playing',
        'requests' => 'Requests',
        'in_library' => 'In library',
        'played' => 'Played',
        'not_found' => 'Not in library',
        'open' => 'Open',
    ],

    'module' => [
        'features' => 'What it does',
        'works_with' => 'Works with',
        'all_modules' => 'All modules',
        'next' => 'Next module',
    ],

    'modules' => [
        'bookings' => [
            'name' => 'Bookings',
            'summary' => 'Appointments, classes, tables, hire and tickets, with online payments and reminders.',
            'tagline' => 'Every way a small business takes bookings, in one place.',
            'intro' => "Set up the services, the staff and the things that get booked, and Bookings works out when there's room. Take a deposit or the full price online, send reminders, and keep a waitlist for when it's full.",
            'highlights' => ['Deposits and gift cards', 'Email, SMS and WhatsApp reminders', 'Waitlists'],
            'features' => [
                [
                    'title' => 'Ways to book',
                    'items' => [
                        'Appointments with a chosen staff member',
                        'Classes and tickets with limited places',
                        'Tables by party size',
                        'Hire by the hour or the day',
                        'Visits, with travel time between jobs',
                        'Enquiries for work that needs a quote',
                    ],
                ],
                [
                    'title' => 'Getting paid',
                    'items' => [
                        'Deposits as a fixed amount or a percentage',
                        'Payment links sent with the booking',
                        'Cash, card and gift cards on the day',
                        'Quotes customers can accept online',
                        'Invoices and refunds, tracked against each booking',
                    ],
                ],
                [
                    'title' => 'Staff and time',
                    'items' => [
                        'Working hours and shifts for each staff member',
                        'Time off and holidays',
                        'Time set aside before and after each booking',
                        'Rooms, chairs, tables or vans as resources',
                    ],
                ],
                [
                    'title' => 'Keeping customers posted',
                    'items' => [
                        'Confirmations and reminders by email, SMS or WhatsApp',
                        'Message templates in the business\'s own words',
                        'A waitlist that fills cancelled places',
                        'Gift cards sold online',
                    ],
                ],
            ],
            'works_with' => [
                'customers' => "Every booking sits on the customer's record, with their history.",
                'website' => "Services and prices show on the business's own site.",
            ],
        ],

        'website' => [
            'name' => 'Website',
            'summary' => "Pages, menus and content for the business's own website.",
            'tagline' => "The content behind the business's website.",
            'intro' => 'Build pages from templates with the fields each section needs, then show them on any website through the content API. Menus, redirects and visitor numbers are looked after in the same place.',
            'highlights' => ['Templates and reusable sections', 'Scheduled publishing', 'Page view analytics'],
            'features' => [
                [
                    'title' => 'Pages',
                    'items' => [
                        'Templates with text, images, links, lists and rich text',
                        'Saved sections to reuse across pages',
                        'Drafts, and pages published or taken down on a schedule',
                        'Titles and descriptions for search engines',
                    ],
                ],
                [
                    'title' => 'Site structure',
                    'items' => [
                        'Menus, put in order by dragging',
                        'Redirects, so old links keep working',
                        'Layouts shared between pages',
                    ],
                ],
                [
                    'title' => 'Publishing',
                    'items' => [
                        'A versioned JSON API that any site can read',
                        'Forms, opening hours and catalogues placed on a page as fields',
                        'Page views counted in the admin, no tracker needed',
                    ],
                ],
                [
                    'title' => 'Files',
                    'items' => [
                        'A file manager for images and documents',
                        'Images picked straight from a page field',
                    ],
                ],
            ],
            'works_with' => [
                'customers' => 'Contact forms on a page send submissions straight in.',
                'catalogue' => 'A menu or price list can sit on any page.',
            ],
        ],

        'customers' => [
            'name' => 'Customers',
            'summary' => 'Contacts, forms and locations, all in one place.',
            'tagline' => 'Who the customers are, and where to find the business.',
            'intro' => "Keep every customer's details and history together. Forms on the website feed straight in, with spam filtered out, and each location's address and opening hours are kept here for the site to show.",
            'highlights' => ['Fields that fit the industry', 'Spam-checked forms', 'Opening hours'],
            'features' => [
                [
                    'title' => 'Contacts',
                    'items' => [
                        'Contact records with addresses and notes',
                        'Fields that fit the industry, like allergies, a colour formula or a vehicle registration',
                        'Health and dietary details marked as sensitive',
                    ],
                ],
                [
                    'title' => 'Forms',
                    'items' => [
                        'Forms built from the fields the business needs',
                        'Submissions saved against the right contact',
                        'Spam caught by Cloudflare Turnstile, timing checks and hidden fields',
                    ],
                ],
                [
                    'title' => 'Locations',
                    'items' => [
                        'Premises, mobile, online or by appointment',
                        'Opening hours, with changes for bank holidays',
                        'Social media links for each location',
                    ],
                ],
            ],
            'works_with' => [
                'bookings' => 'See every booking a customer has made.',
                'website' => 'Opening hours and forms show on any page.',
            ],
        ],

        'catalogue' => [
            'name' => 'Catalogue',
            'summary' => 'Menus, price lists and products, kept up to date in one place.',
            'tagline' => 'Change a price once, and it is right everywhere.',
            'intro' => 'Group items into sections, give them variations like sizes or lengths, and tag them for allergens or dietary needs. The catalogue can be shown on its own page, on the business\'s website, or both.',
            'highlights' => ['Sections and variations', 'Allergen and dietary tags', 'A hosted page'],
            'features' => [
                [
                    'title' => 'Building it',
                    'items' => [
                        'Catalogues split into sections',
                        'Items with variations, each with its own price',
                        'Allergen, dietary and custom tags',
                        'Sold out and stock levels',
                        'Shown only when it applies, like a lunch menu from 12 till 3',
                    ],
                ],
                [
                    'title' => 'Showing it',
                    'items' => [
                        'A hosted page for each catalogue, ready to link to',
                        'The content API, for the business\'s own site',
                        'Placed on a Website page as a field',
                    ],
                ],
            ],
            'works_with' => [
                'website' => 'Drop a catalogue onto any page.',
                'bookings' => 'An item can be a service that customers book.',
            ],
        ],

        'tracker' => [
            'name' => 'Tracker',
            'summary' => 'Habits, metrics and goals, tracked over time.',
            'tagline' => 'Keep an eye on what matters, day by day.',
            'intro' => 'Set up the things worth keeping track of, from a daily habit to a yearly target, and log them as they happen. Tracker adds them up over each period and shows how each goal is going.',
            'highlights' => ['Daily to yearly goals', 'Counts, weights and times', 'Holidays pause the schedule'],
            'features' => [
                [
                    'title' => 'What to track',
                    'items' => [
                        'Yes or no habits',
                        'Counts, weights, volumes, durations, energy and times of day',
                        'Categories to keep things together',
                    ],
                ],
                [
                    'title' => 'Goals',
                    'items' => [
                        'Daily, weekly, monthly, yearly or one-off',
                        'At least, at most, or somewhere between',
                        'Totals, highs, lows or the latest value',
                    ],
                ],
                [
                    'title' => 'Planning',
                    'items' => [
                        'Repeating tasks that show up when they are due',
                        'Holidays that pause the schedule',
                    ],
                ],
            ],
            'works_with' => [],
        ],

        'dj' => [
            'name' => 'DJ',
            'summary' => 'A music library, gigs and live song requests from the crowd.',
            'tagline' => 'Song requests that know what is in the crate.',
            'intro' => 'Import the library from Rekordbox, set up gigs, and let the crowd request songs from their phones. Requests are matched against the library, and marked as played the moment the track goes live.',
            'highlights' => ['Rekordbox import', 'Live requests and upvotes', 'Guest setlists'],
            'features' => [
                [
                    'title' => 'Library',
                    'items' => [
                        'Imports from Rekordbox, by XML or straight from its database',
                        'Duplicates matched into one track',
                        'Links to Tidal, Spotify, Beatport and SoundCloud',
                    ],
                ],
                [
                    'title' => 'Gigs and requests',
                    'items' => [
                        'Requests open, closed or on a schedule',
                        'A hosted request page, or a form on the DJ\'s own site',
                        'Upvotes, so the floor decides what is next',
                        'Limits per visitor, and a cap on the queue',
                    ],
                ],
                [
                    'title' => 'Setlists',
                    'items' => [
                        'Must-plays, maybes and do-not-plays',
                        'Shared with a couple or a client by guest pass',
                    ],
                ],
                [
                    'title' => 'Now playing',
                    'items' => [
                        'Tracks reported live from the decks',
                        'A tracklist the crowd can follow',
                    ],
                ],
            ],
            'works_with' => [
                'website' => 'Put the request form and tracklist on the DJ\'s own site.',
                'bookings' => 'Take gig enquiries and deposits.',
            ],
        ],
    ],
];
