/** The `schedule` JSON shape on a task definition (see backend `TaskSchedule`). */
export type TaskScheduleData = {
    daysOfWeek?: number[] | null;
    monthsOfYear?: number[] | null;
    daysOfMonth?: number[] | null;
    interval?: number | null;
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
