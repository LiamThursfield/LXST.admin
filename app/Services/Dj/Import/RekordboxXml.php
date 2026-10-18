<?php

namespace App\Services\Dj\Import;

use App\Enums\Dj\MusicalKey;
use App\Services\Dj\Import\Data\ArtistData;
use App\Services\Dj\Import\Data\TrackData;
use App\Services\Dj\Import\Exceptions\InvalidImportFile;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Support\Str;
use Throwable;
use XMLReader;

/**
 * Reads Rekordbox's "Export Collection in xml format" file, streaming it so large libraries fit
 * in memory. See the spike (#111) for how its fields map. The export has no artist IDs, My Tags
 * (except inside comments) or play history.
 */
class RekordboxXml implements ImportSource
{
    /**
     * @throws InvalidImportFile
     */
    public function __construct(protected string $path)
    {
        if (! self::isRekordboxXml($path)) {
            throw new InvalidImportFile(__('dj.imports.errors.not_rekordbox_xml'));
        }
    }

    /**
     * Whether the file starts like a Rekordbox export: a DJ_PLAYLISTS root with a rekordbox PRODUCT
     */
    public static function isRekordboxXml(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $reader = self::open($path);
            $isRekordbox = self::nextElementName($reader) === 'DJ_PLAYLISTS'
                && self::nextElementName($reader) === 'PRODUCT'
                && strtolower((string) $reader->getAttribute('Name')) === 'rekordbox';
            $reader->close();

            return $isRekordbox;
        } catch (Throwable) {
            return false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function trackCount(): int
    {
        $count = 0;

        foreach ($this->collectionTracks() as $_) {
            $count++;
        }

        return $count;
    }

    /**
     * The export names artists rather than giving them IDs, so each distinct name (ignoring case and
     * surrounding spaces) is an artist
     *
     * @return Generator<int, ArtistData>
     */
    public function artists(): Generator
    {
        $seen = [];

        foreach ($this->collectionTracks() as $attributes) {
            $name = self::text($attributes['Artist'] ?? null);

            if ($name === null) {
                continue;
            }

            $id = self::artistId($name);

            if (! isset($seen[$id])) {
                $seen[$id] = true;

                yield new ArtistData($id, $name);
            }
        }
    }

    /**
     * My Tags are only in the export inside comments, which are kept as they are
     *
     * @return list<never>
     */
    public function tags(): array
    {
        return [];
    }

    /**
     * @return Generator<int, list<TrackData>>
     */
    public function trackChunks(int $size): Generator
    {
        $chunk = [];

        foreach ($this->collectionTracks() as $attributes) {
            $artist = self::text($attributes['Artist'] ?? null);
            $rating = (int) ($attributes['Rating'] ?? 0);

            $chunk[] = new TrackData(
                sourceTrackId: (string) ($attributes['TrackID'] ?? ''),
                title: trim((string) ($attributes['Name'] ?? '')),
                sourceArtistId: $artist === null ? null : self::artistId($artist),
                album: self::text($attributes['Album'] ?? null),
                genre: self::text($attributes['Genre'] ?? null),
                label: self::text($attributes['Label'] ?? null),
                year: (int) ($attributes['Year'] ?? 0) > 0 ? (int) $attributes['Year'] : null,
                bpm: (float) ($attributes['AverageBpm'] ?? 0) > 0 ? round((float) $attributes['AverageBpm'], 2) : null,
                duration: (int) ($attributes['TotalTime'] ?? 0) > 0 ? (int) $attributes['TotalTime'] : null,
                key: MusicalKey::fromNotation(self::text($attributes['Tonality'] ?? null)),
                comments: self::text($attributes['Comments'] ?? null),
                playCount: max(0, (int) ($attributes['PlayCount'] ?? 0)),
                // 0 to 255, in steps of 51 per star
                rating: $rating > 0 ? min(5, (int) round($rating / 51)) : null,
                dateAdded: self::date($attributes['DateAdded'] ?? null),
                location: self::location($attributes['Location'] ?? null),
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
     * The export has no play history; saved history playlists have no play times
     *
     * @return list<never>
     */
    public function historySessions(): array
    {
        return [];
    }

    /**
     * Each COLLECTION track's attributes. Playlists also contain TRACK elements (references by Key),
     * which are left out.
     *
     * @return Generator<int, array<string, string>>
     *
     * @throws InvalidImportFile
     */
    protected function collectionTracks(): Generator
    {
        $previous = libxml_use_internal_errors(true);
        $reader = self::open($this->path);
        $inCollection = false;

        try {
            while ($reader->read()) {
                $node = self::node($reader);

                if ($node === [XMLReader::ELEMENT, 'COLLECTION']) {
                    $inCollection = true;
                } elseif ($node === [XMLReader::END_ELEMENT, 'COLLECTION']) {
                    break;
                } elseif ($inCollection && $node === [XMLReader::ELEMENT, 'TRACK']) {
                    $attributes = self::attributes($reader);

                    if (($attributes['TrackID'] ?? '') !== '') {
                        yield $attributes;
                    }
                }
            }

            if (libxml_get_errors() !== []) {
                throw new InvalidImportFile(__('dj.imports.errors.malformed_xml'));
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Opens the file without network access or entity expansion
     */
    protected static function open(string $path): XMLReader
    {
        $reader = new XMLReader;

        if (! $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new InvalidImportFile(__('dj.imports.errors.not_rekordbox_xml'));
        }

        return $reader;
    }

    /**
     * Moves to the next element, returning its name, or null at the end of the file
     *
     * @phpstan-impure
     */
    protected static function nextElementName(XMLReader $reader): ?string
    {
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                return $reader->name;
            }
        }

        return null;
    }

    /**
     * The current node's type and name
     *
     * @return array{0: int, 1: string}
     */
    protected static function node(XMLReader $reader): array
    {
        return [$reader->nodeType, $reader->name];
    }

    /**
     * The current element's attributes
     *
     * @return array<string, string>
     */
    protected static function attributes(XMLReader $reader): array
    {
        $attributes = [];

        while ($reader->moveToNextAttribute()) {
            $attributes[$reader->name] = $reader->value;
        }

        $reader->moveToElement();

        return $attributes;
    }

    /**
     * A stable ID for an artist name, so re-imports find the same artist
     */
    protected static function artistId(string $name): string
    {
        return Str::limit(Str::lower(trim($name)), 250, '');
    }

    /**
     * The same format master.db uses: streaming IDs (tidal:tracks:123) as they are, and file paths
     * without the file://localhost prefix or URL encoding
     */
    protected static function location(?string $location): ?string
    {
        $location = self::text($location);

        if ($location === null) {
            return null;
        }

        $location = (string) preg_replace('#^file://localhost#', '', $location);

        return str_starts_with($location, '/') ? rawurldecode($location) : $location;
    }

    protected static function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected static function date(?string $value): ?CarbonImmutable
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
}
