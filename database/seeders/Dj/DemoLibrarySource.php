<?php

namespace Database\Seeders\Dj;

use App\Services\Dj\Import\Data\ArtistData;
use App\Services\Dj\Import\Data\HistorySessionData;
use App\Services\Dj\Import\Data\TagData;
use App\Services\Dj\Import\Data\TrackData;
use App\Services\Dj\Import\ImportSource;
use App\Services\Dj\Import\RekordboxXml;
use Generator;
use Illuminate\Support\Str;

/**
 * A bundled Rekordbox XML export, with what the XML format can't hold: My Tags, read back from the
 * comments (where Rekordbox writes them), and play history, which the seeder makes relative to now.
 * It's imported through the real LibraryImporter, so matching and history work as for a master.db.
 */
class DemoLibrarySource implements ImportSource
{
    /** Each My Tag in the demo libraries, with its category */
    public const array TAG_CATEGORIES = [
        'Warm Up' => 'Situation',
        'Build up' => 'Situation',
        'Mid Set' => 'Situation',
        'Peak Time' => 'Situation',
        'Main Floor' => 'Situation',
        'Beach Club' => 'Situation',
        'Hip Hop Club' => 'Situation',
        'Wedding' => 'Situation',
        'Vocal' => 'Components',
        'Piano' => 'Components',
        'Percussion' => 'Components',
        'Sub Bass' => 'Components',
        'Uplifting' => 'Moods',
        'Chill' => 'Moods',
        'Dark' => 'Moods',
    ];

    /**
     * @param  list<HistorySessionData>  $history
     */
    public function __construct(protected RekordboxXml $xml, protected array $history = []) {}

    public function trackCount(): int
    {
        return $this->xml->trackCount();
    }

    /**
     * @return Generator<int, ArtistData>
     */
    public function artists(): Generator
    {
        return $this->xml->artists();
    }

    /**
     * @return list<TagData>
     */
    public function tags(): array
    {
        return array_map(
            fn (string $name, string $category) => new TagData(Str::slug($name), $name, $category),
            array_keys(self::TAG_CATEGORIES),
            array_values(self::TAG_CATEGORIES),
        );
    }

    /**
     * @return Generator<int, list<TrackData>>
     */
    public function trackChunks(int $size): Generator
    {
        foreach ($this->xml->trackChunks($size) as $chunk) {
            yield array_map(fn (TrackData $track) => new TrackData(...[
                ...get_object_vars($track),
                'sourceTagIds' => $this->tagIds($track->comments),
            ]), $chunk);
        }
    }

    /**
     * @return list<HistorySessionData>
     */
    public function historySessions(): array
    {
        return $this->history;
    }

    /**
     * The My Tags in a comment, e.g. "/* Peak Time / Vocal *\/"
     *
     * @return list<string>
     */
    protected function tagIds(?string $comments): array
    {
        if ($comments === null || preg_match('#/\*(.*)\*/#', $comments, $matches) !== 1) {
            return [];
        }

        return array_values(array_map(
            fn (string $name) => Str::slug($name),
            array_filter(array_map(trim(...), explode('/', $matches[1])), fn (string $name) => isset(self::TAG_CATEGORIES[$name])),
        ));
    }
}
