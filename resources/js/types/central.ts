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
    modules: Array<{ value: string; label: string }>;
    is_disabled: boolean;
    disabled_at: string | null;
    created_at: string | null;
    actions?: any[];
};
