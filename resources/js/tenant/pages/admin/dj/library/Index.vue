<script setup lang="ts">
import { Head, useForm, usePoll } from '@inertiajs/vue3';
// Imported explicitly, as the generated component types don't include UTooltip
import UTooltip from '@nuxt/ui/components/Tooltip.vue';
import {
    hide,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryController';
import { store } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryImportController';
import RowActions from '@/components/RowActions.vue';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type {
    LibraryData,
    LibraryImportData,
    LibraryImportStatus,
    SelectItem,
} from '@/types';

const props = defineProps<{
    libraries: { data: LibraryData[] };
    imports: {
        data: LibraryImportData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    hasRunningImports: boolean;
    importSources: SelectItem[];
    maxUploadMb: number;
    canImport: boolean;
}>();

const { trans } = useTrans();

function setHidden(library: LibraryData) {
    router.patch(
        hide.url(library.id),
        { hidden: !library.is_hidden_from_requests },
        { preserveScroll: true },
    );
}
const timezone = userTimezone(useAuth().value.user);

const statusColors: Record<
    LibraryImportStatus,
    'neutral' | 'info' | 'success' | 'error'
> = {
    pending: 'neutral',
    processing: 'info',
    completed: 'success',
    failed: 'error',
};

// Check on running imports until they've finished
const { start, stop } = usePoll(
    2000,
    { only: ['libraries', 'imports', 'hasRunningImports'] },
    { autoStart: false },
);

watch(
    () => props.hasRunningImports,
    (running) => (running ? start() : stop()),
    { immediate: true },
);

const NEW_LIBRARY = 'new';

// Libraries only take imports from their own source, as artist IDs differ between sources
const libraryItems = computed(() => [
    ...props.libraries.data
        .filter(
            (library) => library.can_import && library.source === form.source,
        )
        .map((library) => ({ label: library.name, value: String(library.id) })),
    { label: trans('dj.imports.fields.new_library'), value: NEW_LIBRARY },
]);

const importOpen = ref(false);

const form = useForm({
    source: String(props.importSources[0]?.value ?? ''),
    library_id: NEW_LIBRARY,
    library_name: '',
    file: null as File | null,
});

function openImport(library?: LibraryData) {
    form.reset();
    form.clearErrors();
    form.source = library
        ? library.source
        : String(props.importSources[0]?.value ?? '');
    form.library_id = library ? String(library.id) : NEW_LIBRARY;
    importOpen.value = true;
}

const isXml = computed(() => form.source === 'rekordbox_xml');

// A different source needs a different library and file
watch(
    () => form.source,
    () => {
        if (
            !libraryItems.value.some((item) => item.value === form.library_id)
        ) {
            form.library_id = NEW_LIBRARY;
        }

        form.file = null;
    },
);

function onSubmit() {
    form.transform((data) => ({
        source: data.source,
        library_id: data.library_id === NEW_LIBRARY ? null : data.library_id,
        library_name:
            data.library_id === NEW_LIBRARY ? data.library_name : null,
        file: data.file,
    })).submit(store(), {
        forceFormData: true,
        onSuccess: () => {
            importOpen.value = false;
            form.reset();
        },
    });
}

function countSummary(counts: Record<string, number> | null): string[] {
    if (!counts) {
        return [];
    }

    return [
        'tracks_added',
        'tracks_updated',
        'tracks_unchanged',
        'tracks_missing',
        'tracks_skipped',
        'history_sessions',
    ]
        .filter((key) => (counts[key] ?? 0) > 0)
        .map((key) =>
            trans(`dj.imports.counts.${key}`, { count: counts[key] }),
        );
}

const importColumns = [
    { accessorKey: 'library', header: trans('dj.imports.columns.library') },
    { accessorKey: 'status', header: trans('dj.imports.columns.status') },
    { accessorKey: 'results', header: trans('dj.imports.columns.results') },
    { accessorKey: 'created_at', header: trans('dj.imports.columns.started') },
];

function changeImportsPage(page: number) {
    router.get(
        index.url(),
        { imports_page: page },
        { preserveState: true, preserveScroll: true, only: ['imports'] },
    );
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.libraries.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton
                        v-if="canImport"
                        icon="i-lucide-upload"
                        :label="trans('dj.imports.create')"
                        @click="openImport()"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.libraries.title')" />

            <div class="flex flex-col gap-8">
                <p class="text-muted text-sm">
                    {{ trans('dj.libraries.description') }}
                </p>

                <div
                    v-if="libraries.data.length"
                    class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <UCard v-for="library in libraries.data" :key="library.id">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex min-w-0 flex-col gap-1">
                                <span
                                    class="text-highlighted truncate font-semibold"
                                >
                                    {{ library.name }}
                                </span>
                                <UBadge
                                    color="neutral"
                                    variant="subtle"
                                    class="w-fit"
                                >
                                    {{ library.source_label }}
                                </UBadge>
                                <UBadge
                                    v-if="library.is_hidden_from_requests"
                                    color="neutral"
                                    variant="subtle"
                                    icon="i-lucide-eye-off"
                                    class="w-fit"
                                >
                                    {{ trans('dj.requests_visibility.hidden') }}
                                </UBadge>
                            </div>
                            <RowActions
                                v-if="library.actions?.length"
                                :actions="library.actions"
                            />
                        </div>

                        <ul class="text-muted mt-4 flex flex-col gap-1 text-sm">
                            <li>
                                {{
                                    trans('dj.libraries.tracks', {
                                        count: library.tracks_count,
                                    })
                                }}
                                <UTooltip
                                    v-if="library.missing_tracks_count"
                                    :text="trans('dj.libraries.missing_help')"
                                >
                                    <UBadge
                                        color="warning"
                                        variant="subtle"
                                        class="ms-1"
                                    >
                                        {{
                                            trans('dj.libraries.missing', {
                                                count: library.missing_tracks_count,
                                            })
                                        }}
                                    </UBadge>
                                </UTooltip>
                            </li>
                            <li>
                                {{
                                    trans('dj.libraries.artists', {
                                        count: library.artists_count,
                                    })
                                }}
                            </li>
                            <li>
                                {{
                                    trans('dj.libraries.sessions', {
                                        count: library.history_sessions_count,
                                    })
                                }}
                            </li>
                            <li>
                                {{
                                    library.last_imported_at
                                        ? trans('dj.libraries.last_imported', {
                                              date: formatDateTime(
                                                  library.last_imported_at,
                                                  timezone,
                                              ),
                                          })
                                        : trans('dj.libraries.never_imported')
                                }}
                            </li>
                        </ul>

                        <UButton
                            v-if="library.can_update"
                            size="xs"
                            color="neutral"
                            variant="link"
                            class="mt-2 px-0"
                            :icon="
                                library.is_hidden_from_requests
                                    ? 'i-lucide-eye'
                                    : 'i-lucide-eye-off'
                            "
                            :label="
                                library.is_hidden_from_requests
                                    ? trans('dj.requests_visibility.show')
                                    : trans('dj.requests_visibility.hide')
                            "
                            :title="
                                trans('dj.requests_visibility.library_help')
                            "
                            @click="setHidden(library)"
                        />

                        <template v-if="library.can_import" #footer>
                            <UButton
                                icon="i-lucide-upload"
                                color="neutral"
                                variant="outline"
                                :label="trans('dj.libraries.import')"
                                @click="openImport(library)"
                            />
                        </template>
                    </UCard>
                </div>
                <p v-else class="text-muted text-sm">
                    {{ trans('dj.libraries.empty') }}
                </p>

                <div class="flex flex-col gap-4">
                    <h2 class="text-highlighted font-semibold">
                        {{ trans('dj.imports.title') }}
                    </h2>

                    <UTable
                        :columns="importColumns"
                        :data="imports.data"
                        :empty="trans('dj.imports.empty')"
                    >
                        <template #library-cell="{ row }">
                            <div class="flex flex-col">
                                <span class="text-highlighted font-medium">
                                    {{ row.original.library }}
                                </span>
                                <span class="text-muted text-xs">
                                    {{ row.original.original_filename }}
                                </span>
                            </div>
                        </template>

                        <template #status-cell="{ row }">
                            <div class="flex min-w-32 flex-col gap-1">
                                <UBadge
                                    :color="
                                        statusColors[
                                            row.original
                                                .status as LibraryImportStatus
                                        ]
                                    "
                                    variant="subtle"
                                    class="w-fit"
                                >
                                    {{ row.original.status_label }}
                                </UBadge>
                                <UProgress
                                    v-if="row.original.is_running"
                                    :model-value="row.original.progress"
                                    size="xs"
                                />
                            </div>
                        </template>

                        <template #results-cell="{ row }">
                            <span
                                v-if="row.original.error"
                                class="text-error whitespace-normal text-sm"
                            >
                                {{ row.original.error }}
                            </span>
                            <span v-else class="whitespace-normal text-sm">
                                {{
                                    countSummary(row.original.counts).join(', ')
                                }}
                            </span>
                        </template>

                        <template #created_at-cell="{ row }">
                            <div class="flex flex-col">
                                <span>
                                    {{
                                        formatDateTime(
                                            row.original.created_at,
                                            timezone,
                                        )
                                    }}
                                </span>
                                <span
                                    v-if="row.original.user"
                                    class="text-muted text-xs"
                                >
                                    {{
                                        trans('dj.imports.by', {
                                            name: row.original.user,
                                        })
                                    }}
                                </span>
                            </div>
                        </template>
                    </UTable>

                    <div
                        v-if="imports.meta.last_page > 1"
                        class="flex justify-end"
                    >
                        <UPagination
                            :items-per-page="imports.meta.per_page"
                            :page="imports.meta.current_page"
                            :total="imports.meta.total"
                            @update:page="changeImportsPage"
                        />
                    </div>
                </div>
            </div>

            <UModal
                v-model:open="importOpen"
                :title="trans('dj.imports.create')"
            >
                <template #body>
                    <form
                        id="library-import-form"
                        class="flex flex-col gap-4"
                        @submit.prevent="onSubmit"
                    >
                        <UFormField
                            :label="trans('dj.imports.fields.source')"
                            :error="form.errors.source"
                        >
                            <USelect
                                v-model="form.source"
                                :items="importSources"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            :label="trans('dj.imports.fields.library')"
                            :help="trans('dj.imports.fields.library_help')"
                            :error="form.errors.library_id"
                        >
                            <USelect
                                v-model="form.library_id"
                                :items="libraryItems"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            v-if="form.library_id === NEW_LIBRARY"
                            :label="trans('dj.imports.fields.library_name')"
                            :error="form.errors.library_name"
                            required
                        >
                            <UInput
                                v-model="form.library_name"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            :label="trans('dj.imports.fields.file')"
                            :help="
                                trans(
                                    `dj.imports.fields.file_help.${form.source}`,
                                    {
                                        size: maxUploadMb,
                                    },
                                )
                            "
                            :error="form.errors.file"
                            required
                        >
                            <UFileUpload
                                :key="form.source"
                                v-model="form.file"
                                :accept="isXml ? '.xml' : '.db'"
                                layout="list"
                                class="w-full"
                                :label="trans('dj.imports.fields.file')"
                            />
                        </UFormField>

                        <UAlert
                            v-if="isXml"
                            color="neutral"
                            variant="subtle"
                            icon="i-lucide-info"
                            :description="trans('dj.imports.xml_help')"
                        />
                        <UAlert
                            v-else
                            color="neutral"
                            variant="subtle"
                            icon="i-lucide-info"
                            :description="trans('dj.imports.decrypt_help')"
                            :actions="[
                                {
                                    label: trans('dj.imports.decrypt_link'),
                                    to: 'https://pyrekordbox.readthedocs.io/en/latest/',
                                    target: '_blank',
                                    variant: 'link',
                                    trailingIcon: 'i-lucide-external-link',
                                },
                            ]"
                        />
                    </form>
                </template>

                <template #footer>
                    <div class="flex w-full justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            :label="trans('common.actions.cancel')"
                            @click="importOpen = false"
                        />
                        <UButton
                            type="submit"
                            form="library-import-form"
                            :loading="form.processing"
                            :label="trans('dj.imports.start')"
                        />
                    </div>
                </template>
            </UModal>
        </template>
    </UDashboardPanel>
</template>
