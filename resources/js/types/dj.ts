/** A DJ library, as returned by `LibraryResource`. */
export type LibraryData = {
    id: number;
    name: string;
    source: string;
    source_label: string;
    tracks_count: number;
    /** Tracks the latest import no longer had; kept for history. */
    missing_tracks_count: number;
    artists_count: number;
    history_sessions_count: number;
    last_imported_at: string | null;
    can_import: boolean;
    actions?: any[];
};

/** An import's status (see backend `ImportStatus`). */
export type LibraryImportStatus =
    'pending' | 'processing' | 'completed' | 'failed';

/** A library import, as returned by `LibraryImportResource`. */
export type LibraryImportData = {
    id: number;
    library: string;
    source_label: string;
    status: LibraryImportStatus;
    status_label: string;
    is_running: boolean;
    /** 0 to 100. */
    progress: number;
    original_filename: string;
    counts: Record<string, number> | null;
    error: string | null;
    user: string | null;
    created_at: string;
    finished_at: string | null;
};
