import type { SelectItem } from '@/types/ui';

export type OrganisationUnitType = 'company' | 'location' | 'department';

export type AddressData = {
    postcode: string;
    line_1: string;
    line_2: string | null;
    line_3: string | null;
    town_city: string | null;
    county: string | null;
    country: string | null;
};

export type LocationType = 'premises' | 'mobile' | 'online' | 'by_appointment';

/** A period of opening, as HH:MM on the location's clock. */
export type OpeningPeriodData = {
    opens_at: string;
    closes_at: string;
    label: string | null;
};

/** A weekly period, on an ISO day of the week (1 is Monday). */
export type OpeningHourData = OpeningPeriodData & {
    day_of_week: number;
};

/** Dates replacing the weekly hours. No periods means closed. */
export type OpeningHourExceptionData = {
    name: string;
    starts_on: string;
    ends_on: string | null;
    periods: OpeningPeriodData[];
};

export type OrganisationUnitData = {
    id: number;
    type: OrganisationUnitType;
    type_label: string;
    location_type: LocationType | null;
    timezone: string | null;
    service_areas: string[];
    opening_hours?: OpeningHourData[];
    opening_hour_exceptions?: OpeningHourExceptionData[];
    name: string;
    slug: string;
    email: string;
    telephone: string | null;
    mobile_phone: string | null;
    socials: Record<string, string>;
    notification_emails: string[];
    parent_id: number | null;
    parent?: { id: number; name: string } | null;
    address?: (AddressData & { id: number }) | null;
    actions?: any[];
};

/** A possible parent unit, as offered by the organisation unit form. */
export type OrganisationUnitParentOption = SelectItem & {
    type: OrganisationUnitType;
};

/** The select options the organisation unit form needs. */
export type OrganisationUnitFormOptions = {
    types: SelectItem[];
    allowedParentTypes: Record<OrganisationUnitType, OrganisationUnitType[]>;
    parentOptions: OrganisationUnitParentOption[];
    socialMedia: SelectItem[];
    locationTypes: SelectItem[];
};

export type ContactData = {
    id: number;
    title: string | null;
    first_name: string | null;
    last_name: string | null;
    name: string;
    name_with_title: string;
    email: string;
    telephone: string | null;
    is_spam: boolean;
    marketing_email: boolean;
    marketing_sms: boolean;
    marketing_telephone: boolean;
    preferred_channel: string | null;
    sms_opted_out: boolean;
    whatsapp_opted_out: boolean;
    created_at: string | null;
    deleted_at: string | null;
    addresses?: Array<AddressData & { id: number }>;
    actions?: any[];
};

export type FormFieldSettingKey = 'max_length' | 'rows' | 'min' | 'max';

export type FormFieldTypeOption = {
    value: string;
    label: string;
    isContactField: boolean;
    settingKeys: FormFieldSettingKey[];
};

export type FormFieldData = {
    id: number | null;
    type: string;
    type_label?: string;
    name: string;
    slug: string;
    is_required: boolean;
    settings: Partial<Record<FormFieldSettingKey, number | null>>;
};

export type CrmFormData = {
    id: number;
    name: string;
    slug: string;
    email_recipients: string[];
    marketing_email: boolean;
    marketing_sms: boolean;
    marketing_telephone: boolean;
    submit_button_text: string | null;
    redirect_url: string | null;
    success_message: string | null;
    fields_count?: number;
    fields?: FormFieldData[];
    actions?: any[];
};

export type FormSubmissionAnswerData = {
    slug: string;
    label: string;
    type: string;
    type_label: string;
    value: string | number | null;
};

export type FormSubmissionData = {
    id: number;
    is_spam: boolean;
    submitted_at: string;
    form: { id: number; name: string; deleted: boolean };
    contact: {
        id: number;
        name: string;
        email: string;
        deleted: boolean;
    } | null;
    answers: FormSubmissionAnswerData[];
    marketing: Partial<
        Record<
            'marketing_email' | 'marketing_sms' | 'marketing_telephone',
            boolean
        >
    >;
    actions?: any[];
};
