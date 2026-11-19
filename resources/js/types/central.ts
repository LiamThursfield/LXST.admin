/** A tenant's domain, with a link to the tenant's admin on it (none for a bare subdomain). */
export type TenantDomainData = {
    id: number;
    domain: string;
    admin_url: string | null;
};

/** A tenant, as returned by the central `TenantResource`. */
export type TenantSetupStatus = 'setting_up' | 'ready' | 'failed';

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
    /** Where setting up its database has got to; only a ready tenant's site answers. */
    setup_status: { value: TenantSetupStatus; label: string };
    setup_error: string | null;
    /** Still setting up after a few minutes, which usually means no queue worker is running. */
    setup_stalled: boolean;
    /** Who's invited as its first admin once it's set up. */
    setup_admin_email: string | null;
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

/** What the central dashboard shows, from the central `DashboardQuery`. */
export type CentralDashboardData = {
    stats: { active: number; disabled: number; new: number; new_days: number };
    queue: { pending: number; failed: number };
    /** Active tenants with something to fix, then the disabled ones */
    attention: Array<{
        id: string;
        name: string;
        /** Keys of `dashboard.issues` */
        issues: Array<'stripe' | 'no_fee' | 'no_own_domain' | 'disabled'>;
        disabled_at: string | null;
    }>;
    recent: Array<{
        id: string;
        name: string;
        industry: string;
        is_disabled: boolean;
        created_at: string;
    }>;
    modules: Array<{ value: string; label: string; count: number }>;
    industries: Array<{ value: string; label: string; count: number }>;
};
