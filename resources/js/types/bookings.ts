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
    required_resource_types: string[] | null;
    is_bookable_online: boolean;
    options?: ServiceOptionData[];
    location_ids?: number[];
    resource_names?: string[];
    actions?: any[];
};

/** A booking type, with which details of a service apply to it. */
export type ServiceBookingTypeOption = SelectItem & {
    details: string[];
    default_resource_types: string[];
};

/** The select options the service form needs. */
export type ServiceFormOptions = {
    bookingTypes: ServiceBookingTypeOption[];
    priceTypes: SelectItem[];
    hireUnits: SelectItem[];
    resourceTypes: SelectItem[];
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

export type WorkingHourData = {
    day_of_week: number;
    starts_at: string;
    ends_at: string;
};

/** A service a resource can do, with any price or length of its own. */
export type ResourceServiceData = {
    id: number;
    price: string | null;
    duration_minutes: number | null;
};

export type BookableResourceData = {
    id: number;
    type: string;
    type_label: string;
    name: string;
    slug: string;
    description: string | null;
    image_file_id: number | null;
    location_id: number | null;
    location_name?: string | null;
    user_id: number | null;
    job_title: string | null;
    capacity: number | null;
    is_active: boolean;
    is_public: boolean;
    services_count: number | null;
    working_hours?: WorkingHourData[];
    services?: ResourceServiceData[];
    actions?: any[];
};

/** The select options the resource form needs. */
export type BookableResourceFormOptions = {
    /** People, rather than spaces, equipment and vehicles. */
    forStaff: boolean;
    types: SelectItem[];
    hasLocations: boolean;
    locations: SelectItem[];
    users: SelectItem[];
    services: SelectItem[];
    currency: string;
};

export type SchedulePeriod = {
    starts_at: string;
    ends_at: string;
    note: string | null;
};

/** One date on the rota: off is approved time off, rota has shifts, weekly is the usual hours. */
export type ScheduleDay = {
    source: 'weekly' | 'rota' | 'off';
    periods: SchedulePeriod[];
};

export type RotaResource = {
    id: number;
    name: string;
    type_label: string;
    schedule: Record<string, ScheduleDay>;
};

export type TimeOffData = {
    id: number;
    resource_name: string | null;
    type_label: string;
    status: string;
    status_label: string;
    starts_on: string;
    ends_on: string;
    reason: string | null;
    actions?: any[];
};

export type StaffHoursRow = {
    id: number;
    name: string;
    job_title: string | null;
    hours: number;
    days_off: number;
};

export type BookingData = {
    id: number;
    when: string | null;
    ends: string | null;
    timezone: string;
    status: string;
    status_label: string;
    needs_time: boolean;
    next_statuses: string[];
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    contact_id: number | null;
    service_name: string;
    location_name?: string | null;
    resource_names?: string[];
    quantity: number;
    price_type: string;
    price: string | null;
    notes: string | null;
    options?: string[];
    actions?: any[];
};

/** A service as the booking form needs it. */
export type BookableServiceOption = {
    value: number;
    label: string;
    booking_type: string;
    hire_unit: string | null;
    min_quantity: number;
    max_quantity: number | null;
    location_ids: number[];
    options: Array<{ value: number; label: string; type: string }>;
    resource_types: Array<{
        value: string;
        label: string;
        resources: SelectItem[];
    }>;
};

export type ServiceSessionData = {
    id: number;
    service_id: number;
    service_name: string | null;
    location_id: number | null;
    location_name: string | null;
    when: string;
    date: string;
    time: string;
    duration_minutes: number;
    capacity: number | null;
    places_left: number;
    resource_ids: number[];
    resource_names: string;
    actions?: any[];
};

/** The select options the timetable form needs. */
export type ServiceSessionFormOptions = {
    services: Array<
        SelectItem & {
            duration_minutes: number | null;
            capacity: number | null;
        }
    >;
    hasLocations: boolean;
    locations: SelectItem[];
    resources: SelectItem[];
};
