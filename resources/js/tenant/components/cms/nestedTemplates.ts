import type { InjectionKey, Ref } from 'vue';
import type {
    ContentValues,
    SavedComponentOption,
    SavedComponentOptions,
    TemplateData,
} from '@/types';

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

/** The key a component's value uses to refer to a saved component, in place of its own values. */
export const savedComponentKey = '_saved_component_id';

/** Whether a component's value is a reference to a saved component, even with nothing chosen yet. */
export function isSavedReference(value: unknown): value is ContentValues {
    return !!value && typeof value === 'object' && savedComponentKey in value;
}

/** What a component's value has in it: a saved component's content if it refers to one. */
export function resolveSavedValues(
    values: ContentValues | null | undefined,
    savedComponents: SavedComponentOption[],
): ContentValues | null | undefined {
    return isSavedReference(values)
        ? savedComponents.find((item) => item.id === values[savedComponentKey])
              ?.content
        : values;
}

type SavedComponentsContext = {
    items: Ref<SavedComponentOption[]>;
    canManage: boolean;
    /** What choosing not to use a saved component is called, e.g. "Custom for this page". */
    customLabel: string;
};

const savedComponentsKey: InjectionKey<SavedComponentsContext> =
    Symbol('cmsSavedComponents');

/**
 * The saved components component fields can use, provided by the page and layout editors. A saved
 * component's own editor doesn't provide them, as it can't use other saved components.
 */
export function provideSavedComponents(
    options: SavedComponentOptions | null | undefined,
    customLabel: string,
): void {
    provide(savedComponentsKey, {
        items: ref(options?.items ?? []),
        canManage: options?.canManage ?? false,
        customLabel,
    });
}

/** The saved components, or null where they can't be used. */
export function useSavedComponents(): SavedComponentsContext | null {
    return inject(savedComponentsKey, null);
}

/** Just the saved components' list, for what describes a component's content. */
export function useSavedComponentItems() {
    const context = useSavedComponents();

    return computed(() => context?.items.value ?? []);
}
