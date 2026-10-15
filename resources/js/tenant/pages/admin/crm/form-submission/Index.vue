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

const searchFilter = ref({
    form_id: props.filter?.form_id ?? 'any',
    contact_id: props.filter?.contact_id ?? '',
    is_spam: props.filter?.is_spam ?? 'any',
    submitted_from: props.filter?.submitted_from ?? '',
    submitted_to: props.filter?.submitted_to ?? '',
});

/** The contact being filtered to, taken from the first result (filtering by contact comes from the contact page). */
const filteredContact = computed(() =>
    searchFilter.value.contact_id ? props.submissions.data[0]?.contact : null,
);

const search = useDebounceFn((page: number = 1) => {
    const query = new URLSearchParams();
    const filter: Record<string, string> = {};

    for (const [key, value] of Object.entries(searchFilter.value)) {
        if (value !== '' && value !== 'any') {
            filter[key] = value;
            query.append(`filter[${key}]`, value);
        }
    }

    let url = `${window.location.pathname}`;
    if (query.size > 0) {
        url += `?${query.toString()}`;
    }
    window.history.pushState({}, '', url);

    router.get(
        index.url(),
        {
            page,
            filter,
        },
        { preserveState: true, replace: true },
    );
}, 300);

watch(searchFilter, () => search(), { deep: true });
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('crm.submissions.index.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <div class="flex flex-col gap-8">
                <div class="flex flex-wrap items-center gap-4 px-2 py-4">
                    <USelect
                        class="w-full max-w-56"
                        :items="formItems"
                        v-model="searchFilter.form_id"
                    />
                    <USelect
                        class="w-full max-w-44"
                        :items="spamItems"
                        v-model="searchFilter.is_spam"
                    />
                    <UInput
                        type="date"
                        class="w-40"
                        :aria-label="trans('crm.submissions.filters.from')"
                        v-model="searchFilter.submitted_from"
                    />
                    <UInput
                        type="date"
                        class="w-40"
                        :aria-label="trans('crm.submissions.filters.to')"
                        v-model="searchFilter.submitted_to"
                    />
                    <UButton
                        v-if="searchFilter.contact_id"
                        color="neutral"
                        variant="subtle"
                        trailing-icon="i-lucide-x"
                        :label="
                            filteredContact
                                ? trans('crm.submissions.filters.contact', {
                                      name:
                                          filteredContact.name ||
                                          filteredContact.email,
                                  })
                                : trans('crm.submissions.filters.clear_contact')
                        "
                        @click="searchFilter.contact_id = ''"
                    />
                </div>

                <UTable :columns="columns" :data="submissions.data">
                    <template #submitted_at-cell="{ row }">
                        {{
                            formatDateTime(row.original.submitted_at, timezone)
                        }}
                    </template>

                    <template #form-cell="{ row }">
                        {{ row.original.form.name }}
                        <span
                            v-if="row.original.form.deleted"
                            class="text-muted"
                        >
                            {{ trans('crm.submissions.show.deleted') }}
                        </span>
                    </template>

                    <template #contact-cell="{ row }">
                        <Link
                            v-if="
                                row.original.contact &&
                                !row.original.contact.deleted
                            "
                            :href="showContact.url(row.original.contact.id)"
                            class="hover:text-primary"
                        >
                            {{
                                row.original.contact.name ||
                                row.original.contact.email
                            }}
                        </Link>
                        <span
                            v-else-if="row.original.contact"
                            class="text-muted"
                        >
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
                </UTable>

                <template v-if="submissions.meta.last_page > 1">
                    <div class="mt-4 flex justify-end">
                        <UPagination
                            :items-per-page="submissions.meta.per_page"
                            :model-value="submissions.meta.current_page"
                            :total="submissions.meta.total"
                            @update:page="(page: any) => search(page)"
                        />
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>
