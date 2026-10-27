import { useHttp } from '@inertiajs/vue3';
import type { MaybeRefOrGetter } from 'vue';
import { show } from '@/actions/App/Http/Controllers/Tenant/Admin/FileManager/FilePickerController';
import type { FileManagerFile } from '@/types';

/** Files already asked for, so a list showing the same image asks once. */
const requests = new Map<number, Promise<FileManagerFile | null>>();

/**
 * A File Manager file's details (its URL, name, whether it's an image) by id, e.g. for a
 * thumbnail. It's null while loading, without an id, or if the file has gone.
 */
export function useFileDetails(
    fileId: MaybeRefOrGetter<number | null | undefined>,
) {
    const http = useHttp();
    const file = ref<FileManagerFile | null>(null);

    function load(id: number): Promise<FileManagerFile | null> {
        let request = requests.get(id);

        if (!request) {
            request = (
                http.get(show.url(id)) as Promise<{ data: FileManagerFile }>
            )
                .then((response) => response.data)
                .catch(() => {
                    requests.delete(id);

                    return null;
                });
            requests.set(id, request);
        }

        return request;
    }

    watch(
        () => toValue(fileId),
        async (id) => {
            if (id === null || id === undefined) {
                file.value = null;

                return;
            }

            const loaded = await load(id);

            if (toValue(fileId) === id) {
                file.value = loaded;
            }
        },
        { immediate: true },
    );

    return file;
}
