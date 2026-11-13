import { useTrans } from '@/composables/useTrans';

/** An amount in minor units as money, e.g. 650 as "£6.50". */
export function formatCatalogueMoney(minor: number, currency: string): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
    }).format(minor / 100);
}

/** An item's price to show, e.g. "£6.50", "from £6.50", or "No price". */
export function catalogueItemPrice(
    price: { amount: number | null; from: boolean } | undefined,
    currency: string,
): string {
    const { trans } = useTrans();

    if (!price || price.amount === null) {
        return trans('catalogue.items.no_price');
    }

    const money = formatCatalogueMoney(price.amount, currency);

    return price.from
        ? trans('catalogue.items.price_from', { price: money })
        : money;
}
