import type { InjectionKey, Ref } from 'vue';
import type { TemplateData } from '@/types';

/**
 * The component and repeater templates (with their fields), provided by the page so nested
 * content editors can look up the fields for the template a field uses.
 */
export const nestedTemplatesKey: InjectionKey<Ref<TemplateData[]>> =
    Symbol('cmsNestedTemplates');

export function provideNestedTemplates(templates: Ref<TemplateData[]>): void {
    provide(nestedTemplatesKey, templates);
}

/** The template a component or repeater field uses, if it's been provided. */
export function useNestedTemplate(templateId: () => number | null | undefined) {
    const templates = inject(nestedTemplatesKey, ref([]));

    return computed(() =>
        (templates.value ?? []).find(
            (template) => template.id === templateId(),
        ),
    );
}

/** The menus a menu field can choose from, provided by the page. */
export const menuOptionsKey: InjectionKey<
    Ref<Array<{ value: number; label: string }>>
> = Symbol('cmsMenuOptions');

export function provideMenuOptions(
    menus: Ref<Array<{ value: number; label: string }>>,
): void {
    provide(menuOptionsKey, menus);
}

export function useMenuOptions() {
    return inject(menuOptionsKey, ref([]));
}

export type CrmOptions = {
    forms: Array<{ value: number; label: string }>;
    organisationUnits: Array<{ value: number; label: string; type: string }>;
};

/** The CRM forms and organisation units CRM fields can choose from, or null without CRM. */
export const crmOptionsKey: InjectionKey<Ref<CrmOptions | null>> =
    Symbol('cmsCrmOptions');

export function provideCrmOptions(options: Ref<CrmOptions | null>): void {
    provide(crmOptionsKey, options);
}

export function useCrmOptions() {
    return inject(crmOptionsKey, ref(null));
}
