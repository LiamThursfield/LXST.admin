import type { SelectItem, TaskOccurrenceStatus } from '@/types';

/** Short display symbols for each backend `TrackerUnit` value. */
const unitSymbols: Record<string, string> = {
    kg: 'kg',
    lb: 'lb',
    l: 'L',
    ml: 'ml',
    minutes: 'min',
    hours: 'h',
    kcal: 'kcal',
    count: '',
    boolean: '',
    'time-of-day': '',
};

export function unitSymbol(unit: string): string {
    return unitSymbols[unit] ?? unit;
}

/**
 * Time-of-day values are stored as seconds since midnight; format as HH:MM.
 */
export function formatTimeOfDay(seconds: number): string {
    const hours = Math.floor(seconds / 3600) % 24;
    const minutes = Math.floor((seconds % 3600) / 60);

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
}

/**
 * Parse an `HH:MM` input into seconds since midnight.
 */
export function parseTimeOfDay(value: string): number {
    const [hours, minutes] = value.split(':').map(Number);

    return (hours || 0) * 3600 + (minutes || 0) * 60;
}

/**
 * Format a display-unit value for its unit, e.g. `1.5 L` or `06:15`.
 */
export function formatValue(
    value: number | null | undefined,
    unit: string,
): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (unit === 'time-of-day') {
        return formatTimeOfDay(value);
    }

    const rounded = Number(value.toFixed(2));
    const symbol = unitSymbol(unit);

    return symbol ? `${rounded} ${symbol}` : `${rounded}`;
}

/** Badge colour and label for each occurrence status. */
export const occurrenceStatusBadges: Record<
    TaskOccurrenceStatus,
    {
        label: string;
        color: 'success' | 'error' | 'info' | 'neutral' | 'warning';
    }
> = {
    met: { label: 'Met', color: 'success' },
    missed: { label: 'Missed', color: 'error' },
    'on-track': { label: 'On track', color: 'info' },
    pending: { label: 'Pending', color: 'neutral' },
    skipped: { label: 'Skipped', color: 'warning' },
};

/**
 * Day-of-week options for scheduling.
 *
 * The values are ISO-8601 weekday numbers (1 = Monday … 7 = Sunday) and MUST
 * match the backend `App\Data\Tracker\TaskSchedule`, which filters on
 * `Carbon::isoWeekday()`. Do not redeclare these with the JS-native 0 = Sunday
 * convention anywhere.
 */
export const daysOfWeek: SelectItem[] = [
    { label: 'Mon', value: 1 },
    { label: 'Tue', value: 2 },
    { label: 'Wed', value: 3 },
    { label: 'Thu', value: 4 },
    { label: 'Fri', value: 5 },
    { label: 'Sat', value: 6 },
    { label: 'Sun', value: 7 },
];

/**
 * Month-of-year options for scheduling (1 = January … 12 = December), matching
 * the backend `TaskSchedule` month filter.
 */
export const monthsOfYear: SelectItem[] = [
    { label: 'Jan', value: 1 },
    { label: 'Feb', value: 2 },
    { label: 'Mar', value: 3 },
    { label: 'Apr', value: 4 },
    { label: 'May', value: 5 },
    { label: 'Jun', value: 6 },
    { label: 'Jul', value: 7 },
    { label: 'Aug', value: 8 },
    { label: 'Sep', value: 9 },
    { label: 'Oct', value: 10 },
    { label: 'Nov', value: 11 },
    { label: 'Dec', value: 12 },
];
