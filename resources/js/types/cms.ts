/** A redirect's status (see backend `RedirectStatus`). */
export type RedirectStatus = 'active' | 'scheduled' | 'expired' | 'disabled';

/** A redirect, as returned by `RedirectResource`. */
export type RedirectData = {
    id: number;
    url_from: string;
    /** A path on the site, or a full URL when `is_external`. */
    url_to: string;
    is_external: boolean;
    is_enabled: boolean;
    is_permanent: boolean;
    http_status: 301 | 302;
    status: RedirectStatus;
    status_label: string;
    published_at: string | null;
    expired_at: string | null;
    /** The dates on the user's clock, for datetime-local inputs. */
    published_at_local: string | null;
    expired_at_local: string | null;
    actions?: any[];
};
