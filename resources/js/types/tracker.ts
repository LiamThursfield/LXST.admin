/** The `schedule` JSON shape on a task definition (see backend `TaskSchedule`). */
export type TaskScheduleData = {
    daysOfWeek?: number[] | null;
    monthsOfYear?: number[] | null;
    daysOfMonth?: number[] | null;
    interval?: number | null;
};

/** An occurrence's evaluation status (see backend `TaskOccurrenceStatus`). */
export type TaskOccurrenceStatus =
    | 'pending'
    | 'on-track'
    | 'met'
    | 'missed'
    | 'skipped';

/** A logged entry, with its value in the task's display unit. */
export type TaskEntryData = {
    id: number;
    value: number;
    label?: string | null;
    occurred_at: string;
};

/**
 * An occurrence as returned by the backend `TaskOccurrenceResource`, with a
 * summary of its task. All values are in the task's display unit.
 */
export type TaskOccurrenceData = {
    id: number;
    period_key: string;
    period_start: string;
    period_end?: string | null;
    status: TaskOccurrenceStatus;
    is_holiday: boolean;
    computed_value?: number | null;
    definition: {
        id: number;
        title: string;
        period: string;
        measure: string;
        unit: string;
        comparator: string;
        aggregation: string;
        target_min?: number | null;
        target_max?: number | null;
        stepped: boolean;
        category?: {
            name: string;
            colour?: string | null;
            icon?: string | null;
        } | null;
    };
    entries: TaskEntryData[];
};

/**
 * A task definition as returned by the backend `TaskDefinitionResource`.
 * Targets are already expressed in the definition's display unit.
 */
export type TaskDefinitionData = {
    id: number;
    title: string;
    description?: string | null;
    category_id?: number | null;
    period: string;
    schedule?: TaskScheduleData | null;
    measure: string;
    unit: string;
    comparator: string;
    aggregation: string;
    target_min?: number | null;
    target_max?: number | null;
    stepped: boolean;
    counts_during_holiday: boolean;
    active_from: string;
    active_to?: string | null;
    status: string;
    sort_order?: number;
};
