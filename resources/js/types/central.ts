/** A tenant's domain, with a link to the tenant's admin on it (none for a bare subdomain). */
export type TenantDomainData = {
    id: number;
    domain: string;
    admin_url: string | null;
};

/** A tenant, as returned by the central `TenantResource`. */
export type TenantData = {
    id: string;
    name: string;
    domains: TenantDomainData[];
    /** The tenant's admin on its first full domain, if it has one. */
    admin_url: string | null;
    industry: { value: string; label: string };
    modules: Array<{ value: string; label: string }>;
    platform_fee_percent: string | null;
    platform_fee_fixed: string | null;
    stripe_connected: boolean;
    is_disabled: boolean;
    disabled_at: string | null;
    created_at: string | null;
    actions?: any[];
};

/** A central admin user, as returned by the central `UserResource`. */
export type CentralUserData = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    /** Whether this is the logged in user */
    is_you: boolean;
    actions?: any[];
};

/** An industry a tenant can be in, as offered on the central tenant form. */
export type TenantIndustryOption = {
    value: string;
    label: string;
    description: string;
    suggested_modules: string[];
};
