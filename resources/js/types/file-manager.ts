export type FolderTreeNode = {
    id: number;
    name: string;
    children: FolderTreeNode[];
};

export type FolderSummary = {
    id: number;
    name: string;
};

export type FileManagerFile = {
    id: number;
    folder_id: number | null;
    name: string;
    path: string;
    url: string;
    mime_type: string | null;
    size: number;
    alt_text: string | null;
    is_image: boolean;
    created_at: string | null;
};

/** What the file picker gets back when it opens a folder. */
export type FilePickerFolder = {
    currentFolder: FolderSummary | null;
    breadcrumbs: FolderSummary[];
    subfolders: FolderSummary[];
    files: FileManagerFile[];
    canUpload: boolean;
};
