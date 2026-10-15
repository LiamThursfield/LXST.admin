import { useTrans } from '@/composables/useTrans';
import type { SelectItem, TaskOccurrenceStatus } from '@/types';

/**
 * Backend `TrackerUnit` values that have a short display symbol,
 * found in lang/{locale}/tracker.php under `units.symbols`.
 */
const unitsWithSymbols = ['kg', 'lb', 'l', 'ml', 'minutes', 'hours', 'kcal'];

/** Units that are shown without a symbol. */
const unitsWithoutSymbols = ['count', 'boolean', 'time-of-day'];

export function unitSymbol(unit: string): string {
    if (unitsWithoutSymbols.includes(unit)) {
        return '';
    }

    if (!unitsWithSymbols.includes(unit)) {
        return unit;
    }

    return useTrans().trans(`tracker.units.symbols.${unit}`);
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

/** Badge colour for each occurrence status. */
const occurrenceStatusColours: Record<
    TaskOccurrenceStatus,
    'success' | 'error' | 'info' | 'neutral' | 'warning'
> = {
    met: 'success',
    missed: 'error',
    'on-track': 'info',
    pending: 'neutral',
    skipped: 'warning',
};

/** Badge colour and translated label for an occurrence status. */
export function occurrenceStatusBadge(status: TaskOccurrenceStatus): {
    label: string;
    color: 'success' | 'error' | 'info' | 'neutral' | 'warning';
} {
    return {
        label: useTrans().trans(`tracker.occurrence_statuses.${status}`),
        color: occurrenceStatusColours[status],
    };
}

/**
 * Day-of-week options for scheduling.
 *
 * The values are ISO-8601 weekday numbers (1 = Monday … 7 = Sunday) and MUST
 * match the backend `App\Data\Tracker\TaskSchedule`, which filters on
 * `Carbon::isoWeekday()`. Do not redeclare these with the JS-native 0 = Sunday
 * convention anywhere.
 */
export function daysOfWeek(): SelectItem[] {
    const { trans } = useTrans();

    return [1, 2, 3, 4, 5, 6, 7].map((day) => ({
        label: trans(`tracker.days_of_week.${day}`),
        value: day,
    }));
}

/**
 * Month-of-year options for scheduling (1 = January … 12 = December), matching
 * the backend `TaskSchedule` month filter.
 */
export function monthsOfYear(): SelectItem[] {
    const { trans } = useTrans();

    return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map((month) => ({
        label: trans(`tracker.months_of_year.${month}`),
        value: month,
    }));
}
