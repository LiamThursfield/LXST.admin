/** A row action, as `HasActions` gives it (see backend `Action`). */
type RowAction = { url: string; method: string };

/**
 * Where a list row's name links: its edit page when the user can edit it, or else its page. A
 * record with a record page (`toRecordPage`) links to that page instead, which has Edit on it.
 * Read from the row's actions, so it follows the same permissions as its actions menu.
 */
export function recordUrl(
    actions: RowAction[] | undefined,
    toRecordPage = false,
): string | null {
    const visits = (actions ?? []).filter((action) => action.method === 'get');
    const isEdit = (action: RowAction) => action.url.endsWith('/edit');

    return (
        (toRecordPage
            ? visits.find((action) => !isEdit(action))
            : visits.find(isEdit)
        )?.url ??
        visits[0]?.url ??
        null
    );
}
