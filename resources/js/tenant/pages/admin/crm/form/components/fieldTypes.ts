/** Each CRM form field type's icon in the builder. Contact fields are grouped by `isContactField`. */
export const formFieldTypeIcons: Record<string, string> = {
    text: 'i-lucide-type',
    textarea: 'i-lucide-align-left',
    number: 'i-lucide-hash',
    email: 'i-lucide-at-sign',
    hidden: 'i-lucide-eye-off',
    crm_email: 'i-lucide-mail',
    crm_title: 'i-lucide-badge',
    crm_first_name: 'i-lucide-user',
    crm_last_name: 'i-lucide-user',
    crm_telephone: 'i-lucide-phone',
};

/** A type the map doesn't know yet still gets an icon. */
export function formFieldTypeIcon(type: string): string {
    return formFieldTypeIcons[type] ?? 'i-lucide-square';
}
