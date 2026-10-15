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

export type OrganisationUnitData = {
    id: number;
    type: OrganisationUnitType;
    type_label: string;
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
};
