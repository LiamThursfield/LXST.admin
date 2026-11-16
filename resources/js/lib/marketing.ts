/**
 * The public site on the central domain (lang/en/marketing.php): its modules, and the sample data
 * shown in the product mock-ups.
 */

import { usePage } from '@inertiajs/vue3';
import { useTrans } from '@/composables/useTrans';

export type MarketingModule =
    'bookings' | 'website' | 'customers' | 'catalogue' | 'tracker' | 'dj';

/** In the order the site lists them, as in MarketingController::MODULES */
export const marketingModules: MarketingModule[] = [
    'bookings',
    'website',
    'customers',
    'catalogue',
    'tracker',
    'dj',
];

export type MarketingFeatureGroup = {
    title: string;
    items: string[];
};

export const moduleIcons: Record<MarketingModule, string> = {
    bookings: 'i-lucide-calendar-check',
    website: 'i-lucide-globe',
    customers: 'i-lucide-users',
    catalogue: 'i-lucide-book-open',
    tracker: 'i-lucide-chart-line',
    dj: 'i-lucide-disc-3',
};

/**
 * Reads a list or group of lines from the shared translations, which trans() can't return
 * because it only gives back strings. Returns an empty list when the key is missing.
 */
export function useTranslationValue() {
    const page = usePage();

    return <T>(key: string, fallback: T): T => {
        let value: unknown = page.props.translations;

        for (const segment of key.split('.')) {
            if (value == null || typeof value !== 'object') {
                return fallback;
            }

            value = (value as Record<string, unknown>)[segment];
        }

        return (value ?? fallback) as T;
    };
}

/**
 * The words a business in each industry uses, from lang/en/industries.php
 */
export type IndustryTerm =
    | 'customer'
    | 'booking'
    | 'staff_member'
    | 'service'
    | 'resource'
    | 'location'
    | 'catalogue';

export function useIndustryTerms() {
    const { trans } = useTrans();

    return (industry: string, term: IndustryTerm, plural = true): string => {
        const form = plural ? 'plural' : 'singular';
        const own = `industries.terminology.${industry}.${term}.${form}`;
        const line = trans(own);

        return line === own ? trans(`industries.terms.${term}.${form}`) : line;
    };
}

export function capitalise(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

export type DemoIndustry =
    'beauty' | 'hospitality' | 'fitness' | 'health' | 'trades' | 'pets';

export type DemoStatus =
    'confirmed' | 'deposit_paid' | 'checked_in' | 'requested' | 'full';

export type DemoBooking = {
    time: string;
    title: string;
    detail: string;
    status: DemoStatus;
};

export type DemoBusiness = {
    name: string;
    domain: string;
    count: number;
    paidOnline: string;
    newCustomers: number;
    bookings: DemoBooking[];
};

export const demoIndustries: DemoIndustry[] = [
    'beauty',
    'hospitality',
    'fitness',
    'health',
    'trades',
    'pets',
];

/**
 * A made-up business for each industry in the home page demo
 */
export const demoBusinesses: Record<DemoIndustry, DemoBusiness> = {
    beauty: {
        name: 'Hollow Oak Hair',
        domain: 'hollowoakhair.co.uk',
        count: 14,
        paidOnline: '£186.00',
        newCustomers: 6,
        bookings: [
            {
                time: '09:30',
                title: 'Priya Shah',
                detail: 'Cut and blow-dry · Jess',
                status: 'checked_in',
            },
            {
                time: '10:15',
                title: 'Tom Reid',
                detail: 'Full head colour · Jess',
                status: 'deposit_paid',
            },
            {
                time: '11:45',
                title: 'Ama Mensah',
                detail: 'Gel manicure · Rosa',
                status: 'confirmed',
            },
            {
                time: '13:30',
                title: 'Leah Walsh',
                detail: 'Balayage · Marco',
                status: 'deposit_paid',
            },
            {
                time: '15:00',
                title: 'Sam Ortiz',
                detail: 'Beard trim · Marco',
                status: 'requested',
            },
        ],
    },
    hospitality: {
        name: 'The Copper Pan',
        domain: 'thecopperpan.co.uk',
        count: 31,
        paidOnline: '£240.00',
        newCustomers: 19,
        bookings: [
            {
                time: '12:00',
                title: 'Okafor, 4 guests',
                detail: 'Table 6 · Window',
                status: 'checked_in',
            },
            {
                time: '12:30',
                title: 'Brennan, 2 guests',
                detail: 'Table 2',
                status: 'confirmed',
            },
            {
                time: '13:00',
                title: 'Lindqvist, 6 guests',
                detail: 'Table 9 · Birthday',
                status: 'deposit_paid',
            },
            {
                time: '19:00',
                title: 'Patel, 12 guests',
                detail: 'Private dining room',
                status: 'deposit_paid',
            },
            {
                time: '19:45',
                title: 'Moreau, 2 guests',
                detail: 'Table 4',
                status: 'requested',
            },
        ],
    },
    fitness: {
        name: 'North Yard',
        domain: 'northyard.fit',
        count: 9,
        paidOnline: '£312.00',
        newCustomers: 11,
        bookings: [
            {
                time: '06:30',
                title: 'Spin',
                detail: '14 of 16 places · Dani',
                status: 'confirmed',
            },
            {
                time: '09:00',
                title: 'Personal training',
                detail: 'Chris Hall · Ben',
                status: 'checked_in',
            },
            {
                time: '12:15',
                title: 'Lunchtime HIIT',
                detail: '9 of 20 places · Dani',
                status: 'confirmed',
            },
            {
                time: '18:00',
                title: 'Bouldering intro',
                detail: '6 of 8 places · Kit',
                status: 'deposit_paid',
            },
            {
                time: '19:30',
                title: 'Yoga flow',
                detail: '18 of 18 places · 3 waiting',
                status: 'full',
            },
        ],
    },
    health: {
        name: 'Riverside Physio',
        domain: 'riversidephysio.co.uk',
        count: 12,
        paidOnline: '£420.00',
        newCustomers: 4,
        bookings: [
            {
                time: '08:45',
                title: 'Martin Cole',
                detail: 'Initial assessment · Room 1',
                status: 'checked_in',
            },
            {
                time: '09:30',
                title: 'Grace Lowe',
                detail: 'Sports massage · Room 2',
                status: 'confirmed',
            },
            {
                time: '11:00',
                title: 'Idris Bello',
                detail: 'Follow-up · Room 1',
                status: 'confirmed',
            },
            {
                time: '14:15',
                title: 'Hannah Kerr',
                detail: 'Acupuncture · Room 3',
                status: 'deposit_paid',
            },
            {
                time: '16:00',
                title: 'Owen Price',
                detail: 'Initial assessment · Room 1',
                status: 'requested',
            },
        ],
    },
    trades: {
        name: 'Fenwick Plumbing',
        domain: 'fenwickplumbing.co.uk',
        count: 5,
        paidOnline: '£95.00',
        newCustomers: 3,
        bookings: [
            {
                time: '08:00',
                title: 'Boiler service',
                detail: '14 Elm Road · Van 2',
                status: 'checked_in',
            },
            {
                time: '10:30',
                title: 'Leak under sink',
                detail: '3 Mill Lane · Van 2',
                status: 'confirmed',
            },
            {
                time: '12:30',
                title: 'Radiator swap',
                detail: '27 Hope Street · Van 1',
                status: 'deposit_paid',
            },
            {
                time: '14:30',
                title: 'Bathroom quote',
                detail: '8 Kings Close · Van 1',
                status: 'requested',
            },
            {
                time: '16:00',
                title: 'Tap replacement',
                detail: '41 Park View · Van 2',
                status: 'confirmed',
            },
        ],
    },
    pets: {
        name: 'Muddy Paws',
        domain: 'muddypaws.dog',
        count: 8,
        paidOnline: '£150.00',
        newCustomers: 5,
        bookings: [
            {
                time: '09:00',
                title: 'Biscuit, cockapoo',
                detail: 'Full groom · Nina',
                status: 'checked_in',
            },
            {
                time: '10:30',
                title: 'Otto, dachshund',
                detail: 'Bath and nails · Nina',
                status: 'confirmed',
            },
            {
                time: '11:30',
                title: 'Mabel, spaniel',
                detail: 'Puppy intro · Joe',
                status: 'deposit_paid',
            },
            {
                time: '13:00',
                title: 'Rolo, labrador',
                detail: 'De-shed · Joe',
                status: 'confirmed',
            },
            {
                time: '14:30',
                title: 'Pip, terrier',
                detail: 'Hand strip · Nina',
                status: 'requested',
            },
        ],
    },
};

export const statusColours: Record<
    DemoStatus,
    'success' | 'primary' | 'neutral' | 'warning' | 'info'
> = {
    checked_in: 'success',
    confirmed: 'neutral',
    deposit_paid: 'primary',
    requested: 'info',
    full: 'warning',
};
