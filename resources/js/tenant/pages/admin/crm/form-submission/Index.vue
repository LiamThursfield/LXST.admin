<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show as showContact } from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/ContactController';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/FormSubmissionController';
import RowActions from '@/components/RowActions.vue';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { FormSubmissionData, SelectItem } from '@/types';

const props = defineProps<{
    filter?: {
        form_id?: string;
        contact_id?: string;
        is_spam?: string;
        submitted_from?: string;
        submitted_to?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    submissions: {
        data: FormSubmissionData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    forms: SelectItem[];
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

/** 'any' means no filter for the select inputs. */
const formItems = computed(() => [
    { label: trans('crm.submissions.filters.form'), value: 'any' },
    ...props.forms,
]);

const spamItems = [
    { label: trans('crm.submissions.filters.any'), value: 'any' },
    { label: trans('crm.submissions.filters.genuine_only'), value: '0' },
    { label: trans('crm.submissions.filters.spam_only'), value: '1' },
];

/** The contact being filtered to, taken from the first result (filtering by contact comes from the contact page). */
const filteredContact = computed(() =>
    props.filter?.contact_id ? props.submissions.data[0]?.contact : null,
);

const { filters, hasFilters, chips, remove, clear, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        form_id: props.filter?.form_id ?? 'any',
        contact_id: props.filter?.contact_id ?? '',
        is_spam: props.filter?.is_spam ?? 'any',
        submitted_from: props.filter?.submitted_from ?? '',
        submitted_to: props.filter?.submitted_to ?? '',
    },
    blanks: { form_id: 'any', is_spam: 'any' },
    labels: {
        form_id: {
            label: trans('crm.submissions.filters.form_label'),
            items: () => props.forms,
        },
        is_spam: {
            label: trans('crm.submissions.filters.spam_label'),
            items: spamItems,
        },
        submitted_from: {
            label: trans('crm.submissions.filters.from'),
            format: (date) =>
                trans('crm.submissions.filters.from_chip', {
                    date: String(date),
                }),
        },
        submitted_to: {
            label: trans('crm.submissions.filters.to'),
            format: (date) =>
                trans('crm.submissions.filters.to_chip', {
                    date: String(date),
                }),
        },
        // Filtering by contact comes from the contact's page; the contact is read from the first result
        contact_id: {
            label: trans('crm.submissions.filters.a_contact'),
            format: () =>
                filteredContact.value
                    ? trans('crm.submissions.filters.contact', {
                          name:
                              filteredContact.value.name ||
                              filteredContact.value.email,
                      })
                    : trans('crm.submissions.filters.a_contact'),
        },
    },
});
</script>

<template>
    <ListLayout
        :title="trans('crm.submissions.index.title')"
        :columns="columns"
        :rows="submissions.data"
        :pagination="submissions.meta"
        :chips="chips"
        :has-filters="hasFilters"
        :empty-title="trans('crm.submissions.index.empty')"
        @remove-filter="remove"
        @clear-filters="clear"
        @page="goToPage"
    >
        <template #filters>
            <USelect
                v-model="filters.form_id"
                class="w-full sm:w-56"
                :items="formItems"
                :aria-label="trans('crm.submissions.filters.form_label')"
            />
            <USelect
                v-model="filters.is_spam"
                class="w-full sm:w-44"
                :items="spamItems"
                :aria-label="trans('crm.submissions.filters.spam_label')"
            />
            <UInput
                v-model="filters.submitted_from"
                type="date"
                class="w-40"
                :aria-label="trans('crm.submissions.filters.from')"
            />
            <UInput
                v-model="filters.submitted_to"
                type="date"
                class="w-40"
                :aria-label="trans('crm.submissions.filters.to')"
            />
        </template>

        <template #submitted_at-cell="{ row }">
            <ListRecordLink :actions="row.original.actions">
                {{ formatDateTime(row.original.submitted_at, timezone) }}
            </ListRecordLink>
        </template>

        <template #form-cell="{ row }">
            {{ row.original.form.name }}
            <span v-if="row.original.form.deleted" class="text-muted">
                {{ trans('crm.submissions.show.deleted') }}
            </span>
        </template>

        <template #contact-cell="{ row }">
            <Link
                v-if="row.original.contact && !row.original.contact.deleted"
                :href="showContact.url(row.original.contact.id)"
                class="hover:text-primary"
            >
                {{ row.original.contact.name || row.original.contact.email }}
            </Link>
            <span v-else-if="row.original.contact" class="text-muted">
                {{ row.original.contact.email }}
                {{ trans('crm.submissions.show.deleted') }}
            </span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #is_spam-cell="{ row }">
            <UBadge
                :color="row.original.is_spam ? 'error' : 'success'"
                variant="subtle"
            >
                {{
                    row.original.is_spam
                        ? trans('crm.submissions.status.spam')
                        : trans('crm.submissions.status.genuine')
                }}
            </UBadge>
        </template>

        <template #actions-cell="{ row }">
            <RowActions
                v-if="row.original.actions"
                :actions="row.original.actions"
            />
        </template>
    </ListLayout>
</template>
