<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/ApiTokenController';
import RowActions from '@/components/RowActions.vue';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';
import type { ApiTokenData, NewApiToken } from '@/types';

const props = defineProps<{
    filter?: {
        search?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    tokens: {
        data: ApiTokenData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    abilities: Array<{ label: string; value: string }>;
    canCreate: boolean;
}>();

const { trans } = useTrans();
const toast = useToast();
const timezone = userTimezone(useAuth().value.user);

const searchFilter = ref({
    search: props.filter?.search || '',
});

const search = useDebounceFn((page: number = 1) => {
    const filter: Record<string, string> = {};

    if (searchFilter.value.search.length) {
        filter.search = searchFilter.value.search;
    }

    router.get(
        index.url(),
        { page, filter },
        { preserveState: true, replace: true },
    );
}, 300);

watch(searchFilter, () => search(), { deep: true });

const createOpen = ref(false);

const form = useForm({
    name: '',
    abilities: [] as string[],
    expires_on: '',
});

function openCreate() {
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function onSubmit() {
    form.submit(store(), {
        onSuccess: () => {
            createOpen.value = false;
            form.reset();
        },
    });
}

// The plain token is flashed once, straight after it's created, and never sent again
const newToken = ref<NewApiToken | null>(null);
const newTokenOpen = computed({
    get: () => newToken.value !== null,
    set: (open: boolean) => {
        if (!open) {
            newToken.value = null;
        }
    },
});

const removeFlashListener = router.on('flash', (event) => {
    if (event.detail.flash.apiToken) {
        newToken.value = event.detail.flash.apiToken;
    }
});

onUnmounted(() => removeFlashListener());

async function copyNewToken() {
    if (!newToken.value) {
        return;
    }

    await navigator.clipboard.writeText(newToken.value.token);
    toast.add({
        title: trans('site_settings.api_tokens.created.copied'),
        icon: 'i-lucide-check',
        color: 'success',
    });
}
</script>

<template>
    <SiteSettingsSubLayout>
        <Head :title="trans('site_settings.api_tokens.title')" />

        <UPageCard
            :title="trans('site_settings.api_tokens.title')"
            :description="trans('site_settings.api_tokens.description')"
            variant="naked"
            orientation="horizontal"
        >
            <UButton
                v-if="canCreate"
                icon="i-lucide-plus"
                :label="trans('site_settings.api_tokens.create')"
                class="w-fit lg:ms-auto"
                @click="openCreate"
            />
        </UPageCard>

        <div class="flex flex-col gap-4">
            <UInput
                v-model="searchFilter.search"
                class="max-w-64"
                icon="i-lucide-search"
                :placeholder="trans('site_settings.api_tokens.search')"
            />

            <UTable
                :columns="columns"
                :data="tokens.data"
                :empty="trans('site_settings.api_tokens.empty')"
            >
                <template #name-cell="{ row }">
                    <div class="flex flex-col items-start gap-1">
                        <span class="text-highlighted font-medium">
                            {{ row.original.name }}
                        </span>
                        <div class="flex flex-wrap gap-1">
                            <UBadge
                                v-for="ability in row.original.abilities"
                                :key="ability.value"
                                color="neutral"
                                variant="subtle"
                            >
                                {{ ability.label }}
                            </UBadge>
                        </div>
                    </div>
                </template>

                <template #last_used_at-cell="{ row }">
                    {{
                        row.original.last_used_at
                            ? formatDateTime(
                                  row.original.last_used_at,
                                  timezone,
                              )
                            : trans('site_settings.api_tokens.never')
                    }}
                </template>

                <template #expires_at-cell="{ row }">
                    <UBadge
                        v-if="row.original.is_expired"
                        color="error"
                        variant="subtle"
                    >
                        {{ trans('site_settings.api_tokens.expired') }}
                    </UBadge>
                    <template v-else-if="row.original.expires_at">
                        {{ formatDateTime(row.original.expires_at, timezone) }}
                    </template>
                    <span v-else class="text-muted">
                        {{ trans('site_settings.api_tokens.no_expiry') }}
                    </span>
                </template>

                <template #actions-cell="{ row }">
                    <RowActions
                        v-if="row.original.actions"
                        :actions="row.original.actions"
                    />
                </template>
            </UTable>

            <div v-if="tokens.meta.last_page > 1" class="flex justify-end">
                <UPagination
                    :items-per-page="tokens.meta.per_page"
                    :page="tokens.meta.current_page"
                    :total="tokens.meta.total"
                    @update:page="(page: any) => search(page)"
                />
            </div>
        </div>

        <UModal
            v-model:open="createOpen"
            :title="trans('site_settings.api_tokens.create')"
        >
            <template #body>
                <form
                    id="api-token-form"
                    class="flex flex-col gap-4"
                    @submit.prevent="onSubmit"
                >
                    <UFormField
                        :label="trans('site_settings.api_tokens.fields.name')"
                        :help="
                            trans('site_settings.api_tokens.fields.name_help')
                        "
                        :error="form.errors.name"
                        required
                    >
                        <UInput v-model="form.name" autofocus class="w-full" />
                    </UFormField>

                    <UFormField
                        :label="
                            trans('site_settings.api_tokens.fields.abilities')
                        "
                        :help="
                            trans(
                                'site_settings.api_tokens.fields.abilities_help',
                            )
                        "
                        :error="
                            form.errors.abilities ??
                            Object.entries(form.errors).find(([key]) =>
                                key.startsWith('abilities.'),
                            )?.[1]
                        "
                        required
                    >
                        <UCheckboxGroup
                            v-model="form.abilities"
                            :items="abilities"
                        />
                    </UFormField>

                    <UFormField
                        :label="
                            trans('site_settings.api_tokens.fields.expires_on')
                        "
                        :help="
                            trans(
                                'site_settings.api_tokens.fields.expires_on_help',
                            )
                        "
                        :error="form.errors.expires_on"
                    >
                        <UInput
                            v-model="form.expires_on"
                            type="date"
                            class="w-full"
                        />
                    </UFormField>
                </form>
            </template>

            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :label="trans('common.actions.cancel')"
                        @click="createOpen = false"
                    />
                    <UButton
                        type="submit"
                        form="api-token-form"
                        color="primary"
                        :loading="form.processing"
                        :label="trans('site_settings.api_tokens.create')"
                    />
                </div>
            </template>
        </UModal>

        <UModal
            v-model:open="newTokenOpen"
            :title="trans('site_settings.api_tokens.created.title')"
            :description="
                trans('site_settings.api_tokens.created.description', {
                    name: newToken?.name ?? '',
                })
            "
            :dismissible="false"
        >
            <template #body>
                <div class="flex gap-2">
                    <UInput
                        :model-value="newToken?.token"
                        readonly
                        class="w-full font-mono"
                    />
                    <UButton
                        icon="i-lucide-copy"
                        color="neutral"
                        variant="outline"
                        :label="trans('site_settings.api_tokens.created.copy')"
                        @click="copyNewToken"
                    />
                </div>
            </template>

            <template #footer>
                <div class="flex w-full justify-end">
                    <UButton
                        color="primary"
                        :label="trans('site_settings.api_tokens.created.done')"
                        @click="newTokenOpen = false"
                    />
                </div>
            </template>
        </UModal>
    </SiteSettingsSubLayout>
</template>
