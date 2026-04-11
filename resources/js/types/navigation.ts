import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { NavigationMenuItem } from '@nuxt/ui';
import type { LucideIcon } from 'lucide-vue-next';

/**
 * A NavigationMenu is an array of NavigationMenuItem arrays.
 * It is used by UNavigationMenu (as :items prop) to render a menu.
 */
export type NavigationMenu = Array<NavigationMenuItem[]>;

export type BreadcrumbItem = {
    title: string;
    href?: string;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
};
