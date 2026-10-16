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
