<?php

namespace App\Enums\Dj;

use App\Enums\Traits\HasLabels;

/**
 * A track's musical key. Backed by its Camelot code, so it reads the same whichever notation a
 * library uses, and keys next to each other on the wheel mix harmonically.
 *
 * @see https://mixedinkey.com/camelot-wheel/
 */
enum MusicalKey: string
{
    use HasLabels;

    case AFlatMinor = '1A';
    case BMajor = '1B';
    case EFlatMinor = '2A';
    case FSharpMajor = '2B';
    case BFlatMinor = '3A';
    case DFlatMajor = '3B';
    case FMinor = '4A';
    case AFlatMajor = '4B';
    case CMinor = '5A';
    case EFlatMajor = '5B';
    case GMinor = '6A';
    case BFlatMajor = '6B';
    case DMinor = '7A';
    case FMajor = '7B';
    case AMinor = '8A';
    case CMajor = '8B';
    case EMinor = '9A';
    case GMajor = '9B';
    case BMinor = '10A';
    case DMajor = '10B';
    case FSharpMinor = '11A';
    case AMajor = '11B';
    case CSharpMinor = '12A';
    case EMajor = '12B';

    /**
     * The semitone each note name is above C
     */
    private const array PITCH_CLASSES = [
        'C' => 0, 'B#' => 0,
        'C#' => 1, 'Db' => 1,
        'D' => 2,
        'D#' => 3, 'Eb' => 3,
        'E' => 4, 'Fb' => 4,
        'F' => 5, 'E#' => 5,
        'F#' => 6, 'Gb' => 6,
        'G' => 7,
        'G#' => 8, 'Ab' => 8,
        'A' => 9,
        'A#' => 10, 'Bb' => 10,
        'B' => 11, 'Cb' => 11,
    ];

    /**
     * Reads a key in Camelot (8A), Open Key (1m) or standard notation (Am, A minor, F#, Gb maj,
     * F♯m), as Rekordbox and other DJ software write them. Returns null when it isn't a key.
     */
    public static function fromNotation(?string $notation): ?self
    {
        $notation = trim(str_replace(['♯', '♭'], ['#', 'b'], (string) $notation));

        if ($notation === '') {
            return null;
        }

        // Camelot: 1A to 12B
        if (preg_match('/^(1[0-2]|[1-9])\s*([AB])$/i', $notation, $matches)) {
            return self::from($matches[1].strtoupper($matches[2]));
        }

        // Open Key: 1m to 12d, where 1d is C major and 1m is A minor
        if (preg_match('/^(1[0-2]|[1-9])\s*([md])$/i', $notation, $matches)) {
            return self::fromWheel(((int) $matches[1] + 6) % 12 + 1, strtolower($matches[2]) === 'm');
        }

        // Standard: a note name, then an optional minor or major marker
        if (preg_match('/^([A-G])([#b]?)\s*(m|min|minor|maj|major)?$/i', $notation, $matches)) {
            $note = strtoupper($matches[1]).$matches[2];
            $pitchClass = self::PITCH_CLASSES[$note] ?? null;

            if ($pitchClass === null) {
                return null;
            }

            $isMinor = in_array(strtolower($matches[3] ?? ''), ['m', 'min', 'minor'], true);

            // The relative major sits three semitones above a minor key, and shares its number
            $majorPitchClass = $isMinor ? ($pitchClass + 3) % 12 : $pitchClass;

            return self::fromWheel((7 * $majorPitchClass + 7) % 12 + 1, $isMinor);
        }

        return null;
    }

    /**
     * The Camelot code, e.g. 8A
     */
    public function camelot(): string
    {
        return $this->value;
    }

    /**
     * The Open Key code, e.g. 1m for A minor
     */
    public function openKey(): string
    {
        return (($this->number() + 4) % 12 + 1).($this->isMinor() ? 'm' : 'd');
    }

    /**
     * The key in standard notation, e.g. Am, F#m or Db
     */
    public function standard(): string
    {
        $note = str_replace(['Minor', 'Major', 'Flat', 'Sharp'], ['', '', 'b', '#'], $this->name);

        return $note.($this->isMinor() ? 'm' : '');
    }

    public function isMinor(): bool
    {
        return str_ends_with($this->value, 'A');
    }

    /**
     * Its number on the Camelot wheel, 1 to 12
     */
    public function number(): int
    {
        return (int) $this->value;
    }

    /**
     * The keys that mix harmonically with this one: itself, its neighbours on the Camelot wheel
     * (one number either side, same letter) and its relative major or minor (same number)
     *
     * @return list<self>
     */
    public function compatible(): array
    {
        $letter = $this->isMinor() ? 'A' : 'B';
        $number = $this->number();

        return [
            $this,
            self::from((($number + 10) % 12 + 1).$letter),
            self::from(($number % 12 + 1).$letter),
            self::from($number.($this->isMinor() ? 'B' : 'A')),
        ];
    }

    /**
     * Shows both notations, e.g. "8A (Am)"
     */
    public function getCustomLabel(): string
    {
        return "{$this->camelot()} ({$this->standard()})";
    }

    private static function fromWheel(int $number, bool $isMinor): self
    {
        return self::from($number.($isMinor ? 'A' : 'B'));
    }
}
