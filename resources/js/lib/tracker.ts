import type { SelectItem } from '@/types';

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
