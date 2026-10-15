export type User = {
    id: number;
    first_name: string;
    last_name: string;
    name: string; // computed
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    /** IANA timezone (tenant users only); null until set. */
    timezone?: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
