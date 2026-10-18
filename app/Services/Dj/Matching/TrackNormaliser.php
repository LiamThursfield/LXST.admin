<?php

namespace App\Services\Dj\Matching;

use Illuminate\Support\Str;

/**
 * Works out when two artist names or track titles are the same, ignoring case, punctuation,
 * featured artists and DJ versions of a song (Clean, Dirty, Intro, Extended, Radio Edit, DJ pool
 * tags and so on). Remixes stay separate: a bracket or dash part that names anyone, e.g.
 * "(Owen Gerrard Extended Remix)", is part of the title.
 */
class TrackNormaliser
{
    /**
     * Words that only describe a version of a song. A bracket or dash part made only of these
     * (and years or numbers) is a version tag, so it's left out when matching.
     */
    public const array VERSION_WORDS = [
        'album', 'club', 'clean', 'dirty', 'edit', 'explicit', 'extended', 'hit', 'hype', 'intro',
        'mix', 'original', 'outro', 'quick', 'radio', 'remaster', 'remastered', 'short', 'single',
        'version', 'free', 'download',
        // DJ record pools, which tag their edits, e.g. "DJcity Intro - Dirty"
        'dj', 'djcity', 'city', 'bpm', 'supreme', 'pool', 'zipdj', 'crack4djs',
    ];

    /**
     * A featured artist, in brackets or trailing, e.g. "(feat. Drake)" or " ft. Drake". A trailing
     * one stops at a bracket or dash, so "I Run ft. X (Mav Remix)" keeps its remix.
     */
    private const string FEATURED = '/\s*(?:[\(\[]\s*(?:feat|ft|featuring)\b\.?[^\)\]]*[\)\]]|\s(?:feat|ft|featuring)\b\.?\s[^\(\[]*?(?=\s+[-–—]\s|\s*[\(\[]|$))/iu';

    /**
     * The key two artists share when they're the same, e.g. "JAY-Z ft. Beyoncé" → "jay z"
     */
    public function artistKey(?string $name): string
    {
        $name = (string) preg_replace(self::FEATURED, '', (string) $name);

        return self::key((string) preg_replace('/^the\s+/i', '', trim($name)));
    }

    /**
     * The key two titles share when they're the same song, e.g. "Humble (DJcity Intro - Dirty)"
     * and "HUMBLE." are both "humble", but "Messy - laszewo Remix" stays "messy laszewo remix"
     */
    public function titleKey(string $title): string
    {
        return self::key($this->baseTitle($title));
    }

    /**
     * The title without featured artists or version tags, keeping its own spelling, for naming
     * a canonical track, e.g. "Humble (DJcity Intro - Dirty)" → "Humble"
     */
    public function baseTitle(string $title): string
    {
        $original = trim($title);
        $title = (string) preg_replace(self::FEATURED, '', $title);

        // Version tags in brackets, e.g. "(Extended Mix)" or "[Clean]", including after a dash
        // inside them, e.g. "(FISHER Rework - Extended Mix)" → "(FISHER Rework)"
        $title = (string) preg_replace_callback(
            '/(\s*)([\(\[])([^\)\]]*)([\)\]])/u',
            function (array $matches): string {
                $parts = array_filter(
                    preg_split('/\s+[-–—]\s+/u', trim($matches[3])) ?: [],
                    fn (string $part) => ! $this->isVersionTag($part),
                );

                return $parts === [] ? '' : $matches[1].$matches[2].implode(' - ', $parts).$matches[4];
            },
            $title,
        );

        // Version tags after a dash, e.g. "Messy - Dirty" (the first part is always the title)
        $parts = preg_split('/\s+[-–—]\s+/u', $title) ?: [$title];
        $kept = array_merge(
            [array_shift($parts)],
            array_values(array_filter($parts, fn (string $part) => ! $this->isVersionTag($part))),
        );

        $title = trim((string) preg_replace('/\s+/u', ' ', implode(' - ', $kept)));

        // Never reduce a title to nothing, e.g. a track only called "(Intro)"
        return $title === '' ? $original : $title;
    }

    /**
     * How alike two keys are, from 0 to 1, for suggesting near matches
     */
    public function similarity(string $first, string $second): float
    {
        if ($first === $second) {
            return 1.0;
        }

        $longest = max(mb_strlen($first), mb_strlen($second));

        if ($longest === 0) {
            return 1.0;
        }

        // levenshtein() counts bytes, so compare ASCII keys (see key())
        return 1 - levenshtein($first, $second) / $longest;
    }

    /**
     * Whether two keys only differ in their numbers, e.g. "gunshots 1" and "gunshots 2", which
     * are a series rather than a typo
     */
    public function differOnlyInNumbers(string $first, string $second): bool
    {
        $withoutNumbers = fn (string $key) => trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[0-9]+/', ' ', $key)));

        return $first !== $second && $withoutNumbers($first) === $withoutNumbers($second);
    }

    /**
     * Whether a bracket or dash part only describes a version of the song
     */
    public function isVersionTag(string $part): bool
    {
        $words = array_filter(preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($part))) ?: []);

        if ($words === []) {
            return false;
        }

        $other = array_filter(
            $words,
            fn (string $word) => ! in_array($word, self::VERSION_WORDS, true) && ! ctype_digit($word),
        );

        return $other === [];
    }

    /**
     * Lower case ASCII letters and numbers, single spaced
     */
    protected static function key(string $value): string
    {
        $value = Str::lower(Str::ascii($value));

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9]+/', ' ', $value)));
    }
}
