/** The groups the template builder's type picker lists field types in, in order. */
export const templateFieldTypeGroups = [
    'text',
    'media',
    'structure',
    'crm',
] as const;

export type TemplateFieldTypeGroup = (typeof templateFieldTypeGroups)[number];

/** Each template field type's icon, and the picker group it's listed in. */
export const templateFieldTypeMeta: Record<
    string,
    { icon: string; group: TemplateFieldTypeGroup }
> = {
    text: { icon: 'i-lucide-type', group: 'text' },
    textarea: { icon: 'i-lucide-align-left', group: 'text' },
    wysiwyg: { icon: 'i-lucide-pilcrow', group: 'text' },
    number: { icon: 'i-lucide-hash', group: 'text' },
    image: { icon: 'i-lucide-image', group: 'media' },
    cms_menu: { icon: 'i-lucide-menu', group: 'media' },
    component: { icon: 'i-lucide-box', group: 'structure' },
    repeater: { icon: 'i-lucide-list', group: 'structure' },
    crm_form: { icon: 'i-lucide-clipboard-list', group: 'crm' },
    crm_organisation_unit: { icon: 'i-lucide-building-2', group: 'crm' },
};

/** A type the map doesn't know yet still gets an icon and a group. */
export function templateFieldTypeIcon(type: string): string {
    return templateFieldTypeMeta[type]?.icon ?? 'i-lucide-square';
}
