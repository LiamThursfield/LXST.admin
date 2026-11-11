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
    payment_requirement: string;
    deposit_type: string | null;
    deposit_amount: string | null;
    no_show_fee_type: string | null;
    no_show_fee_amount: string | null;
    late_cancellation_fee_type: string | null;
    late_cancellation_fee_amount: string | null;
    min_notice_minutes: number | null;
    max_advance_days: number | null;
    cancellation_hours: number | null;
    max_per_customer: number | null;
    requires_approval: boolean;
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
    paymentRequirements: SelectItem[];
    depositTypes: SelectItem[];
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
    combine_group: string | null;
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
    channel: string;
    channel_label: string;
    messages?: Array<{
        id: number;
        event_label: string;
        channel_label: string;
        recipient: string | null;
        status: string;
        status_label: string;
        note: string | null;
        when: string | null;
    }>;
    contact_id: number | null;
    service_name: string;
    location_name?: string | null;
    resource_names?: string[];
    quantity: number;
    is_walk_in: boolean;
    series_id: string | null;
    is_late_cancellation: boolean;
    rules_ignored: boolean;
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
    waiting: number | null;
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

/** Something on the calendar, placed in minutes from the start of its column's date. */
export type CalendarItem = {
    kind: 'booking' | 'session';
    id: number;
    title: string | null;
    subtitle?: string;
    status?: string;
    is_walk_in?: boolean;
    booked?: number;
    start: number;
    end: number;
    movable: boolean;
    url: string;
};

/** One resource on one date. */
export type CalendarColumn = {
    key: string;
    resource_id: number;
    resource_name: string;
    type_label: string;
    date: string;
    day_of_week: number;
    off: boolean;
    follows_location: boolean;
    periods: Array<{ start: number; end: number }>;
    items: CalendarItem[];
};

export type WaitlistEntryData = {
    id: number;
    customer_name: string;
    customer_email: string | null;
    service_name: string | null;
    wanted: string | null;
    quantity: number;
    status: string;
    status_label: string;
    place_free: boolean;
    notes: string | null;
    actions?: any[];
};

/** What the booking form starts from when booking someone off the waitlist. */
export type BookingPrefill = {
    waitlist_entry_id: number;
    service_id: number;
    location_id: number | null;
    date: string | null;
    quantity: number;
    customer_name: string;
    customer_email: string | null;
    customer_phone: string | null;
    notes: string | null;
};

export type PaymentRow = {
    id: number;
    when: string | null;
    amount: string;
    fee: string | null;
    method: string;
    method_label: string;
    status: string;
    status_label: string;
    note: string | null;
    purpose: string;
    purpose_label: string;
    refunded: string | null;
    /** In pounds, ready for the amount field, while some can still be refunded. */
    refundable: string | null;
    failure_message: string | null;
    /** The customer's link, while it can still be paid. */
    link: string | null;
    /** The gift card it was taken from, in the admin. */
    gift_card_url: string | null;
    customer_name?: string;
    booking_url?: string;
};

export type BookingPaymentSummary = {
    price: string | null;
    paid: string;
    outstanding: string | null;
    /** Whether everything has been paid. */
    settled: boolean;
    /** In pounds, ready for the amount field. */
    suggested: string | null;
    canRequest: boolean;
    canManage: boolean;
    methods: SelectItem[];
    list: PaymentRow[];
    /** The card kept for fees, if one is saved. */
    card: { label: string; saved_at: string | null } | null;
    /** The customer's link to save a card, while it's open. */
    cardLink: string | null;
    /** The fee that could be charged now, if any. */
    fee: { purpose: string; label: string; suggested: string | null } | null;
};

export type GiftCardRow = {
    id: number;
    code: string;
    /** E.g. £50.00, or the service it's for. */
    value: string;
    /** What's left, or null while it's waiting to be paid for. */
    balance: string | null;
    recipient: string | null;
    expires_on: string;
    status: string;
    status_label: string;
    show_url: string;
};

export type GiftCardUse = {
    id: number;
    when: string | null;
    customer_name: string;
    service_name: string;
    amount: string;
    refunded: string | null;
    booking_url: string;
};

export type GiftCardDetail = GiftCardRow & {
    kind: string;
    kind_label: string;
    /** Null when given free. */
    price_paid: string | null;
    method_label: string;
    fee: string | null;
    purchaser_name: string | null;
    purchaser_email: string | null;
    recipient_name: string | null;
    recipient_email: string | null;
    message: string | null;
    issued_at: string | null;
    sent_at: string | null;
    link: string;
    canSend: boolean;
    canCancel: boolean;
    uses: GiftCardUse[];
};

export type DocumentLineData = {
    description: string;
    /** E.g. 2 or 2.5. */
    quantity: string;
    unit_price: string;
    total: string;
};

/** A quote or invoice as the admin and the customer see it, with amounts formatted. */
export type BookingDocumentData = {
    number: string;
    business: string;
    business_details: string | null;
    tax_number: string | null;
    customer_name: string;
    customer_email: string | null;
    service_name: string;
    when: string | null;
    lines: DocumentLineData[];
    subtotal: string;
    /** E.g. "VAT (20%)", or null when there's no tax. */
    tax_label: string | null;
    tax: string | null;
    total: string;
    notes: string | null;
    /** Invoices only. */
    paid?: string;
    owed?: string;
};

/** A line as the quote and invoice forms edit it, with its price in pounds. */
export type DocumentFormLine = {
    description: string;
    quantity: string;
    unit_price: string;
};

export type DocumentFormBooking = {
    id: number;
    customer_name: string;
    service_name: string;
    when: string | null;
    url: string;
};

export type DocumentRow = {
    id: number;
    number: string;
    customer_name: string;
    service_name: string;
    total: string;
    owed?: string;
    date: string | null;
    status: string;
    status_label: string;
    show_url: string;
};

export type BookingDocumentSummary = {
    quotes: Array<{
        id: number;
        number: string;
        total: string;
        status: string;
        status_label: string;
        url: string;
    }>;
    invoice: {
        id: number;
        number: string;
        total: string;
        owed: string;
        status: string;
        status_label: string;
        url: string;
    } | null;
    canManage: boolean;
};
