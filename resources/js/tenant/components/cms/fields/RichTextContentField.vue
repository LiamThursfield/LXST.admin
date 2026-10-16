<script setup lang="ts">
import type { EditorCustomHandlers, EditorToolbarItem } from '@nuxt/ui';
import type { Editor, JSONContent } from '@tiptap/vue-3';
import FilePickerModal from '@/tenant/components/file-manager/FilePickerModal.vue';
import type { FileManagerFile, TemplateFieldData } from '@/types';

/**
 * Rich text, stored as the editor's TipTap JSON. The HTML sent with it is only a preview: the
 * server rebuilds and sanitises the HTML from the JSON.
 */
type RichTextValue = { json: JSONContent; html: string };

defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<RichTextValue | null>({ default: null });

const { trans } = useTrans();

const editorRef = useTemplateRef<{ editor?: Editor }>('editorRef');

/** The editor whose toolbar opened the link dialog or image picker. */
const activeEditor = shallowRef<Editor | null>(null);

const linkOpen = ref(false);
const linkUrl = ref('');
const pickerOpen = ref(false);

function onUpdate(json: JSONContent) {
    value.value = {
        json,
        html: editorRef.value?.editor?.getHTML() ?? '',
    };
}

/** Our own link and image buttons, replacing the browser prompts the defaults use. */
const handlers: EditorCustomHandlers = {
    link: {
        canExecute: (editor) =>
            editor.can().setLink({ href: '' }) || editor.isActive('link'),
        execute: (editor) => {
            activeEditor.value = editor;
            linkUrl.value = editor.getAttributes('link').href ?? '';
            linkOpen.value = true;

            return editor.chain();
        },
        isActive: (editor) => editor.isActive('link'),
        isDisabled: (editor) =>
            editor.state.selection.empty && !editor.isActive('link'),
    },
    image: {
        canExecute: (editor) => editor.can().setImage({ src: '' }),
        execute: (editor) => {
            activeEditor.value = editor;
            pickerOpen.value = true;

            return editor.chain();
        },
        isActive: (editor) => editor.isActive('image'),
    },
};

function applyLink() {
    const chain = activeEditor.value?.chain().focus().extendMarkRange('link');

    if (!chain) return;

    if (linkUrl.value.trim()) {
        chain.setLink({ href: linkUrl.value.trim() }).run();
    } else {
        chain.unsetLink().run();
    }

    linkOpen.value = false;
}

function removeLink() {
    activeEditor.value
        ?.chain()
        .focus()
        .extendMarkRange('link')
        .unsetLink()
        .run();
    linkOpen.value = false;
}

function insertImage(file: FileManagerFile) {
    activeEditor.value
        ?.chain()
        .focus()
        .setImage({ src: file.url, alt: file.alt_text ?? '' })
        .run();
}

const tip = (key: string, replace?: Record<string, string | number>) => ({
    text: trans(`cms.content.rich_text.toolbar.${key}`, replace),
});

const toolbar = computed<EditorToolbarItem[][]>(() => [
    [
        {
            icon: 'i-lucide-heading',
            tooltip: tip('text_style'),
            content: { align: 'start' },
            items: [
                {
                    kind: 'paragraph',
                    icon: 'i-lucide-pilcrow',
                    label: trans('cms.content.rich_text.toolbar.paragraph'),
                },
                ...[2, 3, 4].map((level) => ({
                    kind: 'heading' as const,
                    level: level as 2 | 3 | 4,
                    icon: `i-lucide-heading-${level}`,
                    label: trans('cms.content.rich_text.toolbar.heading', {
                        level,
                    }),
                })),
            ],
        },
    ],
    [
        {
            kind: 'mark',
            mark: 'bold',
            icon: 'i-lucide-bold',
            tooltip: tip('bold'),
        },
        {
            kind: 'mark',
            mark: 'italic',
            icon: 'i-lucide-italic',
            tooltip: tip('italic'),
        },
        {
            kind: 'mark',
            mark: 'underline',
            icon: 'i-lucide-underline',
            tooltip: tip('underline'),
        },
        {
            kind: 'mark',
            mark: 'strike',
            icon: 'i-lucide-strikethrough',
            tooltip: tip('strike'),
        },
    ],
    [
        {
            kind: 'bulletList',
            icon: 'i-lucide-list',
            tooltip: tip('bullet_list'),
        },
        {
            kind: 'orderedList',
            icon: 'i-lucide-list-ordered',
            tooltip: tip('ordered_list'),
        },
        {
            kind: 'blockquote',
            icon: 'i-lucide-text-quote',
            tooltip: tip('blockquote'),
        },
    ],
    [
        { kind: 'link', icon: 'i-lucide-link', tooltip: tip('link') },
        { kind: 'image', icon: 'i-lucide-image', tooltip: tip('image') },
    ],
    [
        { kind: 'undo', icon: 'i-lucide-undo', tooltip: tip('undo') },
        { kind: 'redo', icon: 'i-lucide-redo', tooltip: tip('redo') },
    ],
]);
</script>

<template>
    <div class="border-default rounded-md border">
        <UEditor
            ref="editorRef"
            v-slot="{ editor }"
            :model-value="value?.json ?? null"
            content-type="json"
            :editable="!disabled"
            :starter-kit="{
                code: false,
                codeBlock: false,
                link: { openOnClick: false },
            }"
            :handlers="handlers"
            :placeholder="trans('cms.content.rich_text.placeholder')"
            class="min-h-40 w-full"
            :ui="{ content: 'px-4 py-3', base: 'min-h-32' }"
            @update:model-value="(json) => onUpdate(json as JSONContent)"
        >
            <UEditorToolbar
                v-if="!disabled"
                :editor="editor"
                :items="toolbar"
                class="border-default overflow-x-auto border-b px-2 py-1"
            />
        </UEditor>

        <UModal
            v-model:open="linkOpen"
            :title="trans('cms.content.rich_text.link.title')"
        >
            <template #body>
                <form class="flex flex-col gap-4" @submit.prevent="applyLink">
                    <UFormField
                        :label="trans('cms.content.rich_text.link.url')"
                        :help="trans('cms.content.rich_text.link.url_help')"
                    >
                        <UInput
                            v-model="linkUrl"
                            autofocus
                            placeholder="https://"
                            class="w-full"
                        />
                    </UFormField>
                    <div class="flex justify-end gap-2">
                        <UButton
                            v-if="activeEditor?.isActive('link')"
                            color="neutral"
                            variant="ghost"
                            :label="trans('cms.content.rich_text.link.remove')"
                            @click="removeLink"
                        />
                        <UButton
                            type="submit"
                            :label="trans('cms.content.rich_text.link.apply')"
                        />
                    </div>
                </form>
            </template>
        </UModal>

        <FilePickerModal
            v-model:open="pickerOpen"
            images-only
            @select="insertImage"
        />
    </div>
</template>
