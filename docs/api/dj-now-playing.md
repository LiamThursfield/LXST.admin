# DJ API: now playing (for the Rekordbox CLI tool)

The CLI tool reports each track as it's played. LXST.admin adds it to the live gig's **played** tracks and marks matching song requests as played.

## Endpoint

```
POST https://{tenant-domain}/api/v1/dj/now-playing
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Authentication

Create a token in **Site settings › API tokens** with the `dj:now-playing` ability. Tokens start with `lxst_`. The token's user must still be able to manage API tokens.

- No token, or an invalid, expired or revoked one: `401`
- A token without `dj:now-playing`: `403`

### When to report

Report a track **once, when it goes live** (it becomes audible on the master output), not when it's loaded or previewed on a deck. Which deck is live is the CLI's call; the API doesn't track decks.

## Request body

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `title` | string, ≤ 255 | Yes | As shown in Rekordbox |
| `artist` | string, ≤ 255 | No | Recommended: unmatched plays and custom requests match by artist and title |
| `source_track_id` | string, ≤ 255 | No | Rekordbox's track ID (`ID` in `master.db`, `TrackID` in the XML). The most reliable way to match the track |
| `library_id` | integer | No | The LXST.admin library the track's from, if there are several. Must exist |
| `played_at` | ISO 8601 date-time | No | When it went live. Defaults to now. At most a day ago, and not in the future |
| `gig` | string | No | A gig's public ID, to report to that gig rather than the current one |
| `bpm`, `key`, `deck` | number, string, integer (1–8) | No | Accepted for the CLI's own records; not used for matching |

Example:

```json
{
  "source_track_id": "170878528",
  "library_id": 1,
  "artist": "Bicep",
  "title": "Glue",
  "played_at": "2026-10-19T22:41:07+01:00",
  "deck": 2
}
```

## What happens

1. **The gig:** the one given in `gig`, otherwise the current gig, which must be pinned as live or on at `played_at`. With no such gig, the report is refused (`409`, see below). Plays outside gigs come in with the next library import instead.
2. **Matching:**
   - the library track with that `source_track_id` (in `library_id`, if given)
   - otherwise the song (canonical track) with the same normalised artist and title
   - otherwise it's kept as an unmatched play with its reported artist and title
3. **The play** is added to the gig's live history session ("Live from Rekordbox", made with the first report), so it shows in the gig's played tracks and History.
4. **Requests:** pending or accepted requests on the gig's list for the same song are marked **played**. A request matches if it's the same library song, or it's a custom request with the same artist and title once normalised.

## Responses

### `201 Created`: recorded

```json
{
  "data": {
    "outcome": "recorded",
    "gig": { "id": "01m58jnz2n71bk18f6xfe479ev", "name": "Fabric Night" },
    "play": { "id": 412, "matched": "library_track", "played_at": "2026-10-19T21:41:07+00:00" },
    "requests_played": 1
  }
}
```

`play.matched` is `library_track`, `song` or `none`.

### `200 OK`: duplicate

The same track reported again within 5 minutes of `played_at` (`dj.now_playing.duplicate_minutes`) is ignored. This makes retries safe: resend a report whose response you didn't get, with the same `played_at`. The response has `outcome: "duplicate"`, the earlier play, and `requests_played: 0`.

### Errors

| Status | When | Body |
| --- | --- | --- |
| `401` | Missing or invalid token | `{ "message": "…" }` |
| `403` | Token without `dj:now-playing` | `{ "message": "…" }` |
| `404` | The tenant doesn't have the DJ module | `{ "message": "…" }` |
| `409` | No gig on or pinned as live | `{ "message": "…", "reason": "no_gig" }` |
| `422` | Invalid body | `{ "message": "…", "errors": { "title": ["…"] } }` |
| `429` | Rate limited | `{ "message": "…" }`, with `Retry-After` |

## Rate limits

Token requests are limited per token (`api.tokens.rate_limit_per_minute`). A track every few seconds is far below it. On `429`, wait for `Retry-After` seconds and resend; duplicates are ignored.
