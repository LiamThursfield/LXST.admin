import type { SelectItem } from '@/types/ui';

export type ServiceOptionData = {
    id: number | null;
    type: string;
    name: string;
    price_adjustment: string;
    duration_adjustment: number;
};

export type ServiceData = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    category_id: number | null;
    category_name?: string | null;
    image_file_id: number | null;
    booking_type: string;
    booking_type_label: string;
    price_type: string;
    /** In major units, e.g. pounds. Null when priced on quote. */
    price: string | null;
    duration_minutes: number | null;
    buffer_before_minutes: number;
    buffer_after_minutes: number;
    capacity: number | null;
    min_party_size: number | null;
    max_party_size: number | null;
    hire_unit: string | null;
    min_hire_units: number | null;
    max_hire_units: number | null;
    travel_minutes: number | null;
    is_bookable_online: boolean;
    options?: ServiceOptionData[];
    location_ids?: number[];
    actions?: any[];
};

/** A booking type, with which details of a service apply to it. */
export type ServiceBookingTypeOption = SelectItem & {
    details: string[];
};

/** The select options the service form needs. */
export type ServiceFormOptions = {
    bookingTypes: ServiceBookingTypeOption[];
    priceTypes: SelectItem[];
    hireUnits: SelectItem[];
    optionTypes: SelectItem[];
    categories: SelectItem[];
    hasLocations: boolean;
    locations: SelectItem[];
    currency: string;
};

export type ServiceCategoryData = {
    id: number;
    name: string;
    order: number;
    services_count: number | null;
    actions?: any[];
};
