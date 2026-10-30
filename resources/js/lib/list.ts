/** A row action, as `HasActions` gives it (see backend `Action`). */
type RowAction = { url: string; method: string };

/**
 * Where a list row's name links: its edit page when the user can edit it, or else its page. Read
 * from the row's actions, so it follows the same permissions as its actions menu.
 */
export function recordUrl(actions: RowAction[] | undefined): string | null {
    const visits = (actions ?? []).filter((action) => action.method === 'get');

    return (
        visits.find((action) => action.url.endsWith('/edit'))?.url ??
        visits[0]?.url ??
        null
    );
}
