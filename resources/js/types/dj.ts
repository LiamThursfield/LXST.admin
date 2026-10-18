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

/** A track's musical key, in each notation. */
export type MusicalKeyData = {
    value: string;
    camelot: string;
    open_key: string;
    standard: string;
    label: string;
};

/** A library track, as returned by `LibraryTrackResource`. */
export type LibraryTrackData = {
    id: number;
    title: string;
    artist: { id: number; name: string } | null;
    library: string;
    genre: string | null;
    bpm: number | null;
    /** e.g. 3:45 */
    duration: string | null;
    key: MusicalKeyData | null;
    play_count: number;
    rating: number | null;
    date_added: string | null;
    service: string | null;
    is_missing: boolean;
    url: string;
};

/** A library track with its canonical record and history, as returned by `LibraryTrackDetailResource`. */
export type LibraryTrackDetailData = LibraryTrackData & {
    album: string | null;
    label: string | null;
    year: number | null;
    comments: string | null;
    location: string | null;
    service_track_id: string | null;
    source_track_id: string;
    missing_since: string | null;
    tags: Array<{ name: string; category: string | null }>;
    canonical: {
        title: string;
        artist: string | null;
        bpm: number | null;
        duration: string | null;
        key: MusicalKeyData | null;
        is_verified: boolean;
    } | null;
    other_sources: Array<{
        id: number;
        title: string;
        library: string;
        source: string;
        is_missing: boolean;
        url: string;
    }>;
    history_plays_count: number;
    history_plays: Array<{ session: string; played_at: string | null }>;
};

/** A library artist, as returned by `LibraryArtistResource`. */
export type LibraryArtistData = {
    id: number;
    name: string;
    library: string;
    tracks_count: number;
    tracks_url: string;
};
