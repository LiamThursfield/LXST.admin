import type { NavigationMenu } from '@/types';

export function getMenu(menuKey: string): NavigationMenu {
    const menu = usePage().props.menus[menuKey];
    if (menu == null) {
        return [];
    }

    return menu;
}

export function usePageMenus() {
    return {
        main: ref<NavigationMenu>(getMenu('main')),
    };
}
