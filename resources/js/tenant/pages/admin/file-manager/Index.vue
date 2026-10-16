<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { TreeItem } from '@nuxt/ui';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/FileManager/FileManagerController';
import { formatBytes } from '@/lib/files';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { FileManagerFile, FolderSummary, FolderTreeNode } from '@/types';
import FileDetailsSlideover from './components/FileDetailsSlideover.vue';
import NewFolderModal from './components/NewFolderModal.vue';
import UploadModal from './components/UploadModal.vue';

const props = defineProps<{
    folderTree: FolderTreeNode[];
    currentFolder: { id: number; name: string; path: string } | null;
    breadcrumbs: FolderSummary[];
    subfolders: FolderSummary[];
    files: {
        data: FileManagerFile[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    canCreateFolders: boolean;
    canUpload: boolean;
    canEditFiles: boolean;
    uploadRules: { extensions: string[]; maxSizeKb: number; maxFiles: number };
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const newFolderOpen = ref(false);
const uploadOpen = ref(false);

/** The file whose details are open; kept up to date as the folder's files reload (e.g. after saving). */
const selectedFileId = ref<number | null>(null);
const detailsOpen = ref(false);
const selectedFile = computed(
    () =>
        props.files.data.find((file) => file.id === selectedFileId.value) ??
        null,
);

function showDetails(file: FileManagerFile) {
    selectedFileId.value = file.id;
    detailsOpen.value = true;
}

const currentLocation = computed(() =>
    [
        trans('file_manager.top_level'),
        ...props.breadcrumbs.map((folder) => folder.name),
    ].join(' / '),
);

/** Opens a folder (or the top level), reloading only its contents. */
function openFolder(folderId: number | null, page = 1) {
    router.get(
        index.url({
            query: {
                ...(folderId ? { folder: String(folderId) } : {}),
                ...(page > 1 ? { page: String(page) } : {}),
            },
        }),
        {},
        {
            only: ['currentFolder', 'breadcrumbs', 'subfolders', 'files'],
            preserveState: true,
            preserveScroll: true,
        },
    );
}

/**
 * The expanded tree items (keyed by folder id, '0' for the top level). The folders containing
 * the current one are always expanded, so the current folder is visible after moving or creating it.
 */
const expanded = ref<string[]>([]);

watch(
    () => props.breadcrumbs,
    (breadcrumbs) => {
        expanded.value = [
            ...new Set([
                ...expanded.value,
                '0',
                ...breadcrumbs.map((folder) => String(folder.id)),
            ]),
        ];
    },
    { immediate: true },
);

function toTreeItems(nodes: FolderTreeNode[]): TreeItem[] {
    return nodes.map((node) => ({
        id: node.id,
        label: node.name,
        icon:
            props.currentFolder?.id === node.id
                ? 'i-lucide-folder-open'
                : 'i-lucide-folder',
        children: node.children.length ? toTreeItems(node.children) : undefined,
        onSelect: () => openFolder(node.id),
    }));
}

const treeItems = computed<TreeItem[]>(() => [
    {
        id: 0,
        label: trans('file_manager.top_level'),
        icon: 'i-lucide-hard-drive',
        children: toTreeItems(props.folderTree),
        onSelect: () => openFolder(null),
    },
]);

const breadcrumbItems = computed(() => [
    {
        label: trans('file_manager.top_level'),
        icon: 'i-lucide-hard-drive',
        onClick: () => openFolder(null),
    },
    ...props.breadcrumbs.map((folder) => ({
        label: folder.name,
        onClick: () => openFolder(folder.id),
    })),
]);

const isEmpty = computed(
    () => !props.subfolders.length && !props.files.data.length,
);
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('file_manager.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton
                        v-if="canUpload"
                        icon="i-lucide-upload"
                        :label="trans('file_manager.files.upload')"
                        @click="uploadOpen = true"
                    />
                    <UButton
                        v-if="canCreateFolders"
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-folder-plus"
                        :label="trans('file_manager.folders.create')"
                        @click="newFolderOpen = true"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('file_manager.title')" />

            <div class="flex flex-col gap-6 lg:flex-row">
                <aside class="lg:w-64 lg:shrink-0">
                    <UTree
                        v-model:expanded="expanded"
                        :items="treeItems"
                        :get-key="(item) => String(item.id)"
                    />
                </aside>

                <section class="flex min-w-0 flex-1 flex-col gap-4">
                    <UBreadcrumb :items="breadcrumbItems" />

                    <p v-if="isEmpty" class="text-muted text-sm">
                        {{ trans('file_manager.files.empty') }}
                    </p>

                    <ul
                        v-else
                        class="divide-default border-default divide-y rounded-md border text-sm"
                    >
                        <li
                            v-for="folder in subfolders"
                            :key="`folder-${folder.id}`"
                        >
                            <button
                                type="button"
                                class="hover:bg-elevated/50 flex w-full items-center gap-3 px-3 py-2 text-left"
                                @click="openFolder(folder.id)"
                            >
                                <UIcon
                                    name="i-lucide-folder"
                                    class="text-primary size-5"
                                />
                                <span class="flex-1 truncate">{{
                                    folder.name
                                }}</span>
                            </button>
                        </li>

                        <li
                            v-for="file in files.data"
                            :key="`file-${file.id}`"
                            class="flex items-center gap-3 px-3 py-2"
                        >
                            <img
                                v-if="file.is_image"
                                :src="file.url"
                                :alt="file.alt_text ?? ''"
                                class="size-8 rounded object-cover"
                            />
                            <UIcon
                                v-else
                                name="i-lucide-file"
                                class="text-muted size-5"
                            />
                            <button
                                type="button"
                                class="hover:text-primary flex-1 truncate text-left"
                                @click="showDetails(file)"
                            >
                                {{ file.name }}
                            </button>
                            <span class="text-muted hidden lg:inline">{{
                                file.mime_type
                            }}</span>
                            <span class="text-muted w-20 text-right">{{
                                formatBytes(file.size)
                            }}</span>
                            <span
                                v-if="file.created_at"
                                class="text-muted hidden w-40 text-right md:inline"
                                >{{
                                    formatDateTime(file.created_at, timezone)
                                }}</span
                            >
                        </li>
                    </ul>

                    <div
                        v-if="files.meta.last_page > 1"
                        class="flex justify-end"
                    >
                        <UPagination
                            :items-per-page="files.meta.per_page"
                            :page="files.meta.current_page"
                            :total="files.meta.total"
                            @update:page="
                                (page: number) =>
                                    openFolder(currentFolder?.id ?? null, page)
                            "
                        />
                    </div>
                </section>
            </div>

            <UploadModal
                v-if="canUpload"
                v-model:open="uploadOpen"
                :folder="currentFolder"
                :rules="uploadRules"
            />

            <FileDetailsSlideover
                v-if="selectedFile"
                v-model:open="detailsOpen"
                :file="selectedFile"
                :location="currentLocation"
                :can-edit="canEditFiles"
            />

            <NewFolderModal
                v-if="canCreateFolders"
                v-model:open="newFolderOpen"
                :parent="currentFolder"
            />
        </template>
    </UDashboardPanel>
</template>
