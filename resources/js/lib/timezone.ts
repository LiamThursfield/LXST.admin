import { router } from '@inertiajs/vue3';
import { update } from '@/routes/admin/settings/timezone';
import type { User } from '@/types';

/** The browser's IANA timezone, e.g. `Europe/London`. */
export function browserTimezone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

/**
 * The timezone tracker days follow: the user's saved timezone, falling back
 * to the browser's until one is saved.
 */
export function userTimezone(user?: User | null): string {
    return user?.timezone || browserTimezone();
}

/** Today's calendar date (Y-m-d) in the given timezone. */
export function todayIn(timezone: string): string {
    // en-CA formats dates as YYYY-MM-DD.
    return new Intl.DateTimeFormat('en-CA', { timeZone: timezone }).format(
        new Date(),
    );
}

/** Shift a calendar date (Y-m-d) by whole days, independent of timezone. */
export function addDays(date: string, days: number): string {
    const day = new Date(`${date}T00:00:00Z`);
    day.setUTCDate(day.getUTCDate() + days);

    return day.toISOString().slice(0, 10);
}

/** Every IANA timezone the browser knows, always including `current`. */
export function timezoneOptions(current?: string | null): string[] {
    const zones = Intl.supportedValuesOf('timeZone');

    return current && !zones.includes(current) ? [current, ...zones] : zones;
}

/**
 * Save the browser's timezone once for a signed-in user who has none yet, so
 * tracker days follow their local midnight rather than UTC.
 */
export function syncBrowserTimezone(): void {
    let sent = false;

    router.on('navigate', (event) => {
        const user = (event.detail.page.props.auth as { user?: User | null })
            ?.user;

        if (sent || !user || user.timezone) {
            return;
        }

        sent = true;
        router.patch(
            update.url(),
            { timezone: browserTimezone() },
            { async: true, preserveScroll: true, preserveState: true },
        );
    });
}

/**
 * Formats an ISO timestamp as a date and time on the given timezone's clock, e.g. "15 Oct 2026, 14:05".
 */
export function formatDateTime(iso: string, timezone: string): string {
    return new Date(iso).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: timezone,
    });
}

/**
 * Formats an ISO timestamp as a time on the given timezone's clock, e.g. "14:05".
 */
export function formatTime(iso: string, timezone: string): string {
    return new Date(iso).toLocaleTimeString(undefined, {
        timeStyle: 'short',
        timeZone: timezone,
    });
}
