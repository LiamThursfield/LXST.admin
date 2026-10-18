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
    /** Hides the songs only in it from song request search. */
    is_hidden_from_requests: boolean;
    last_imported_at: string | null;
    can_import: boolean;
    can_update: boolean;
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
    /** Its song is hidden from song requests, itself or with its artist. */
    is_hidden_from_requests: boolean;
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
        id: number;
        title: string;
        artist: string | null;
        bpm: number | null;
        duration: string | null;
        key: MusicalKeyData | null;
        is_verified: boolean;
        is_hidden_from_requests: boolean;
        is_artist_hidden_from_requests: boolean;
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
    /** Its canonical artist is hidden from song requests. */
    is_hidden_from_requests: boolean;
    /** The artist counted once across libraries. */
    canonical_url: string | null;
};

/** One side of a suggested duplicate pair on the matching page. */
export type MatchCandidate = {
    id: number;
    name: string;
    detail: string | null;
    sources_count: number;
    is_verified: boolean;
    url: string;
};

/** A suggested pair of canonical records that might be the same. */
export type MatchSuggestion = {
    similarity: number;
    first: MatchCandidate;
    second: MatchCandidate;
};

/** A merge or split, as returned by `CanonicalMergeResource`. */
export type CanonicalMergeData = {
    id: number;
    record: 'artist' | 'track';
    record_label: string;
    action: 'merge' | 'split';
    action_label: string;
    source_name: string;
    target_name: string;
    moved_count: number;
    /** Null when the re-match did it. */
    user: string | null;
    created_at: string;
};

/** A canonical track ("song"), as returned by `SongResource`. */
export type SongData = {
    id: number;
    title: string;
    artist: { id: number; name: string } | null;
    bpm: number | null;
    duration: string | null;
    key: MusicalKeyData | null;
    is_verified: boolean;
    is_hidden_from_requests: boolean;
    is_artist_hidden_from_requests: boolean;
    library_tracks_count: number;
    libraries_count: number;
    url: string;
};

/** A canonical artist, as returned by `CanonicalArtistResource`. */
export type CanonicalArtistData = {
    id: number;
    name: string;
    is_verified: boolean;
    is_hidden_from_requests: boolean;
    tracks_count: number;
    library_artists_count: number;
    url: string;
};

/** A gig's request settings (see backend `GigRequestSettings`); null means "use the default". */
export type GigRequestSettingsData = {
    requests_mode?: 'open' | 'closed' | 'scheduled' | null;
    requests_open_minutes_before?: number | null;
    max_requests_per_visitor?: number | null;
    max_pending_requests?: number | null;
    allow_custom_requests?: boolean | null;
    show_public_list?: boolean | null;
    allow_upvotes?: boolean | null;
};

/** The DJ settings' request defaults, as the gig form shows them. */
export type RequestDefaults = {
    requests_mode: 'open' | 'closed' | 'scheduled';
    requests_mode_label: string;
    requests_open_minutes_before: number;
    max_requests_per_visitor: number;
    max_pending_requests: number;
    allow_custom_requests: boolean;
    show_public_list: boolean;
    allow_upvotes: boolean;
};

/** A gig, as returned by `GigResource`. */
export type GigData = {
    id: number;
    /** Random, for request links and the public API. */
    public_id: string;
    name: string;
    venue: string | null;
    location: string | null;
    timezone: string;
    starts_at: string;
    ends_at: string;
    /** On the gig's own clock, for datetime-local inputs. */
    starts_at_local: string;
    ends_at_local: string;
    notes: string | null;
    is_pinned: boolean;
    is_current: boolean;
    is_on_now: boolean;
    is_past: boolean;
    requests_open: boolean;
    request_url: string | null;
    request_settings: GigRequestSettingsData;
    effective_request_settings: Required<GigRequestSettingsData>;
    can_update: boolean;
    actions?: any[];
};

/** A setlist song's type (see backend `SetlistSongType`). */
export type SetlistSongType = 'must_play' | 'maybe' | 'do_not_play';

/** A setlist, as returned by `SetlistResource`. */
export type SetlistData = {
    id: number;
    name: string;
    description: string | null;
    visibility: 'private' | 'public';
    visibility_label: string;
    gig: {
        id: number;
        name: string;
        starts_at: string;
        timezone: string;
        url: string;
    } | null;
    counts: Record<SetlistSongType, number>;
    url: string;
    actions?: any[];
};

/** A song on a setlist, as returned by `SetlistItemResource`. */
export type SetlistItemData = {
    id: number;
    title: string;
    artist: string | null;
    is_custom: boolean;
    song_url: string | null;
    bpm: number | null;
    key: MusicalKeyData | null;
    duration: string | null;
    type: SetlistSongType | null;
    note: string | null;
    added_by: string | null;
    created_at: string;
};

/** A play history session, as returned by `HistorySessionResource`. */
export type HistorySessionData = {
    id: number;
    name: string;
    source: 'import' | 'manual';
    library: string | null;
    started_at: string | null;
    ended_at: string | null;
    plays_count: number;
    include_in_stats: boolean;
    gig: { id: number; name: string; url: string } | null;
    url: string;
};

/** A play, as returned by `PlayedTrackResource`. */
export type PlayedTrackData = {
    id: number;
    title: string | null;
    artist: string | null;
    played_at: string | null;
    session: string;
    is_manual: boolean;
    song_url: string | null;
    track_url: string | null;
};

/** A guest pass, as returned by `GuestPassResource`. */
export type GuestPassData = {
    id: number;
    label: string;
    password: string;
    can_add: boolean;
    status: 'active' | 'expired' | 'revoked';
    /** The last day it works, on the DJ's clock. */
    expires_on: string | null;
    last_used_at: string | null;
    items_count: number;
};
