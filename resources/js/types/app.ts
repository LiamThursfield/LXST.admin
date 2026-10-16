export type App = {
    name: string;
    context: AppContext;
};

export type AppContext = 'central' | 'tenant';

/** The tenant's name and branding (site settings), shared with every tenant page. */
export type SiteBranding = {
    name: string;
    logoUrl: string | null;
    faviconUrl: string | null;
    colors: {
        primary: string;
        neutral: string;
    };
};
