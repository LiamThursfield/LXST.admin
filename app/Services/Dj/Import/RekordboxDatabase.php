<?php

namespace App\Services\Dj\Import;

use App\Enums\Dj\MusicalKey;
use App\Services\Dj\Import\Data\ArtistData;
use App\Services\Dj\Import\Data\HistorySessionData;
use App\Services\Dj\Import\Data\TagData;
use App\Services\Dj\Import\Data\TrackData;
use App\Services\Dj\Import\Exceptions\InvalidImportFile;
use Carbon\CarbonImmutable;
use Generator;
use PDO;
use Pdo\Sqlite;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Reads a decrypted Rekordbox 6 or 7 `master.db`, read-only. Rows Rekordbox has deleted
 * (`rb_local_deleted`) are skipped. See the spike (#111) for how its fields map.
 */
class RekordboxDatabase implements ImportSource
{
    /** The first bytes of every unencrypted SQLite file */
    public const string SQLITE_HEADER = "SQLite format 3\0";

    protected PDO $pdo;

    /**
     * @throws InvalidImportFile
     */
    public function __construct(string $path)
    {
        if (! self::isUnencryptedSqlite($path)) {
            throw new InvalidImportFile(__('dj.imports.errors.not_decrypted'));
        }

        try {
            $this->pdo = new PDO('sqlite:'.$path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                Sqlite::ATTR_OPEN_FLAGS => Sqlite::OPEN_READONLY,
            ]);

            $version = $this->select('select DBVersion from djmdProperty limit 1')->fetchColumn();
        } catch (PDOException) {
            throw new InvalidImportFile(__('dj.imports.errors.not_rekordbox'));
        }

        // Rekordbox 6 and 7 share the 6xxx schema
        if ((! is_string($version) && ! is_int($version)) || ! str_starts_with((string) $version, '6')) {
            throw new InvalidImportFile(__('dj.imports.errors.unsupported_version'));
        }
    }

    /**
     * Whether the file is a plain SQLite database. An encrypted master.db has a random header.
     */
    public static function isUnencryptedSqlite(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, strlen(self::SQLITE_HEADER));
        fclose($handle);

        return $header === self::SQLITE_HEADER;
    }

    public function trackCount(): int
    {
        return (int) $this->select('select count(*) from djmdContent where coalesce(rb_local_deleted, 0) = 0')->fetchColumn();
    }

    /**
     * @return Generator<int, ArtistData>
     */
    public function artists(): Generator
    {
        $rows = $this->select("select ID, Name from djmdArtist where coalesce(rb_local_deleted, 0) = 0 and trim(coalesce(Name, '')) != ''");

        foreach ($rows as $row) {
            yield new ArtistData((string) $row['ID'], trim((string) $row['Name']));
        }
    }

    /**
     * My Tags, with the category each sits under
     *
     * @return Generator<int, TagData>
     */
    public function tags(): Generator
    {
        $rows = $this->select(
            "select tag.ID, tag.Name, category.Name as Category
            from djmdMyTag tag
            left join djmdMyTag category on category.ID = tag.ParentID and category.Attribute = 1
            where tag.Attribute = 0 and coalesce(tag.rb_local_deleted, 0) = 0 and trim(coalesce(tag.Name, '')) != ''"
        );

        foreach ($rows as $row) {
            yield new TagData((string) $row['ID'], trim((string) $row['Name']), self::text($row['Category']));
        }
    }

    /**
     * Tracks in chunks, so the importer can work through them a batch at a time
     *
     * @return Generator<int, list<TrackData>>
     */
    public function trackChunks(int $size): Generator
    {
        $tagIds = $this->tagIdsByTrack();

        $rows = $this->select(
            'select content.ID, content.Title, content.ArtistID, album.Name as Album, genre.Name as Genre,
                label.Name as Label, content.ReleaseYear, content.BPM, content.Length, musicalKey.ScaleName,
                content.Commnt, content.DJPlayCount, content.Rating, content.StockDate, content.FolderPath
            from djmdContent content
            left join djmdAlbum album on album.ID = content.AlbumID
            left join djmdGenre genre on genre.ID = content.GenreID
            left join djmdLabel label on label.ID = content.LabelID
            left join djmdKey musicalKey on musicalKey.ID = content.KeyID
            where coalesce(content.rb_local_deleted, 0) = 0
            order by content.ID'
        );

        $chunk = [];

        foreach ($rows as $row) {
            $id = (string) $row['ID'];

            $chunk[] = new TrackData(
                sourceTrackId: $id,
                title: trim((string) $row['Title']),
                sourceArtistId: self::text($row['ArtistID']),
                album: self::text($row['Album']),
                genre: self::text($row['Genre']),
                label: self::text($row['Label']),
                year: (int) $row['ReleaseYear'] > 0 ? (int) $row['ReleaseYear'] : null,
                // Stored as an integer, 100 times the BPM
                bpm: (int) $row['BPM'] > 0 ? (int) $row['BPM'] / 100 : null,
                duration: (int) $row['Length'] > 0 ? (int) $row['Length'] : null,
                key: MusicalKey::fromNotation(self::text($row['ScaleName'])),
                comments: self::text($row['Commnt']),
                playCount: max(0, (int) $row['DJPlayCount']),
                rating: (int) $row['Rating'] > 0 ? min(5, (int) $row['Rating']) : null,
                dateAdded: self::date($row['StockDate']),
                location: self::text($row['FolderPath']),
                sourceTagIds: $tagIds[$id] ?? [],
            );

            if (count($chunk) >= $size) {
                yield $chunk;
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            yield $chunk;
        }
    }

    /**
     * Play history sessions, with their plays in order. Folders (`Attribute` 1) are left out.
     *
     * @return Generator<int, HistorySessionData>
     */
    public function historySessions(): Generator
    {
        $plays = [];
        $rows = $this->select(
            'select HistoryID, ContentID, TrackNo, created_at from djmdSongHistory
            where coalesce(rb_local_deleted, 0) = 0
            order by HistoryID, TrackNo'
        );

        foreach ($rows as $row) {
            $plays[(string) $row['HistoryID']][] = [
                'source_track_id' => (string) $row['ContentID'],
                'position' => (int) $row['TrackNo'],
                // Unlike djmdHistory.DateCreated (local wall-clock time), this is true UTC
                'played_at' => self::instant($row['created_at']),
            ];
        }

        $sessions = $this->select(
            'select ID, Name from djmdHistory where Attribute = 0 and coalesce(rb_local_deleted, 0) = 0 order by DateCreated'
        );

        foreach ($sessions as $row) {
            $id = (string) $row['ID'];

            yield new HistorySessionData($id, trim((string) $row['Name']), $plays[$id] ?? []);
        }
    }

    /**
     * Runs a query; with exceptions switched on, PDO throws rather than returning false
     */
    protected function select(string $sql): PDOStatement
    {
        $statement = $this->pdo->query($sql);

        if (! $statement instanceof PDOStatement) {
            throw new PDOException('The query could not be run.');
        }

        return $statement;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function tagIdsByTrack(): array
    {
        $tagIds = [];
        $rows = $this->select('select ContentID, MyTagID from djmdSongMyTag where coalesce(rb_local_deleted, 0) = 0');

        foreach ($rows as $row) {
            $tagIds[(string) $row['ContentID']][] = (string) $row['MyTagID'];
        }

        return $tagIds;
    }

    protected static function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected static function date(mixed $value): ?CarbonImmutable
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    protected static function instant(mixed $value): ?CarbonImmutable
    {
        $value = self::text($value);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
