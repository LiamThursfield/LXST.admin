<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { TableColumn } from '@nuxt/ui';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/ContactController';
import {
    index as submissionsIndex,
    show as showSubmission,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/FormSubmissionController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { AddressData, ContactData, FormSubmissionData } from '@/types';

const props = defineProps<{
    contact: { data: ContactData };
    /** The contact's latest submissions, or null if the user can't see submissions. */
    submissions: { data: FormSubmissionData[] } | null;
    submissionsCount: number | null;
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const contact = computed(() => props.contact.data);

const tab = ref('details');

const tabs = computed(() => [
    { value: 'details', label: trans('crm.contacts.show.details') },
    ...(props.submissions
        ? [
              {
                  value: 'submissions',
                  label: trans('crm.contacts.show.submissions'),
                  count: props.submissionsCount ?? 0,
              },
          ]
        : []),
]);

const breadcrumbs = computed(() => [
    { label: trans('crm.contacts.index.title'), to: index.url() },
    { label: contact.value.name || contact.value.email },
]);

const allSubmissionsUrl = computed(() =>
    submissionsIndex.url({
        query: { 'filter[contact_id]': String(contact.value.id) },
    }),
);

const details = computed(() => [
    {
        label: trans('crm.contacts.fields.title'),
        value: contact.value.title,
    },
    {
        label: trans('crm.contacts.fields.first_name'),
        value: contact.value.first_name,
    },
    {
        label: trans('crm.contacts.fields.last_name'),
        value: contact.value.last_name,
    },
    {
        label: trans('crm.contacts.fields.email'),
        value: contact.value.email,
    },
    {
        label: trans('crm.contacts.fields.telephone'),
        value: contact.value.telephone,
    },
]);

const status = computed(() => [
    {
        label: trans('crm.contacts.show.status'),
        value: contact.value.is_spam
            ? trans('crm.contacts.status.spam')
            : trans('crm.contacts.status.genuine'),
        color: contact.value.is_spam ? 'error' : 'success',
    } as const,
    {
        label: trans('crm.contacts.show.added'),
        value: contact.value.created_at
            ? formatDateTime(contact.value.created_at, timezone)
            : null,
    },
    ...(contact.value.deleted_at
        ? [
              {
                  label: trans('crm.contacts.show.deleted'),
                  value: formatDateTime(contact.value.deleted_at, timezone),
              },
          ]
        : []),
]);

const marketing = computed(
    () =>
        (
            ['marketing_email', 'marketing_sms', 'marketing_telephone'] as const
        ).map((key) => ({
            label: trans(`crm.contacts.marketing.${key}`),
            value: contact.value[key]
                ? trans('crm.contacts.show.agreed')
                : trans('crm.contacts.show.not_agreed'),
            color: contact.value[key] ? 'success' : 'neutral',
        })) as Array<{
            label: string;
            value: string;
            color: 'success' | 'neutral';
        }>,
);

/** An address's lines, without the blank ones. */
function addressLines(address: AddressData): string[] {
    return [
        address.line_1,
        address.line_2,
        address.line_3,
        address.town_city,
        address.county,
        address.postcode,
        address.country,
    ].filter((line): line is string => Boolean(line));
}

const submissionColumns: TableColumn<FormSubmissionData>[] = [
    { id: 'form', header: trans('crm.submissions.columns.form') },
    {
        id: 'submitted_at',
        header: trans('crm.submissions.columns.submitted_at'),
    },
    { id: 'status', header: trans('crm.submissions.columns.status') },
];
</script>

<template>
    <RecordLayout
        v-model:tab="tab"
        :title="trans('crm.contacts.show.title', { name: contact.name })"
        :breadcrumbs="breadcrumbs"
        :subtitle="contact.name ? contact.email : null"
        :tabs="tabs"
        :actions="contact.actions"
    >
        <template v-if="contact.is_spam || contact.deleted_at" #badges>
            <UBadge v-if="contact.deleted_at" color="neutral" variant="subtle">
                {{ trans('crm.contacts.status.deleted') }}
            </UBadge>
            <UBadge v-if="contact.is_spam" color="error" variant="subtle">
                {{ trans('crm.contacts.status.spam') }}
            </UBadge>
        </template>

        <template v-if="tab === 'details'">
            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <RecordFacts
                    :title="trans('crm.contacts.show.contact')"
                    :facts="details"
                />

                <UCard variant="subtle">
                    <h2 class="text-highlighted mb-3 text-sm font-semibold">
                        {{ trans('crm.contacts.addresses.title') }}
                    </h2>
                    <p
                        v-if="!contact.addresses?.length"
                        class="text-muted text-sm"
                    >
                        {{ trans('crm.contacts.show.no_addresses') }}
                    </p>
                    <ul v-else class="divide-default divide-y text-sm">
                        <li
                            v-for="address in contact.addresses"
                            :key="address.id"
                            class="py-2 first:pt-0 last:pb-0"
                        >
                            <address class="not-italic">
                                <span
                                    v-for="line in addressLines(address)"
                                    :key="line"
                                    class="block"
                                >
                                    {{ line }}
                                </span>
                            </address>
                        </li>
                    </ul>
                </UCard>
            </div>
        </template>

        <RecordTable
            v-else-if="submissions"
            :columns="submissionColumns"
            :rows="submissions.data"
            :total="submissionsCount"
            :view-all-href="allSubmissionsUrl"
            :empty-title="trans('crm.contacts.show.no_submissions')"
            empty-icon="i-lucide-inbox"
        >
            <template #form-cell="{ row }">
                <Link
                    :href="showSubmission.url(row.original.id)"
                    class="text-highlighted font-medium hover:underline"
                >
                    {{ row.original.form.name }}
                </Link>
            </template>
            <template #submitted_at-cell="{ row }">
                {{ formatDateTime(row.original.submitted_at, timezone) }}
            </template>
            <template #status-cell="{ row }">
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
        </RecordTable>

        <template #sidebar>
            <RecordFacts
                :title="trans('crm.contacts.show.status')"
                :facts="status"
            />
            <RecordFacts
                :title="trans('crm.contacts.marketing.title')"
                :facts="marketing"
            />
        </template>
    </RecordLayout>
</template>
