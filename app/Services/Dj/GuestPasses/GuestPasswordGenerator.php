<?php

namespace App\Services\Dj\GuestPasses;

/**
 * Easy-to-share guest pass passwords, e.g. "maple-river-42", to read out or text to a guest.
 * They're a simple block on open access rather than a secret, so short words are fine.
 */
class GuestPasswordGenerator
{
    /**
     * Short, common words that are easy to spell and hard to mishear
     *
     * @var list<string>
     */
    public const array WORDS = [
        'amber', 'apple', 'aspen', 'autumn', 'bay', 'beach', 'berry', 'birch', 'bloom', 'blue',
        'breeze', 'brook', 'cedar', 'cherry', 'cliff', 'cloud', 'clover', 'coast', 'comet', 'coral',
        'cove', 'daisy', 'dawn', 'disco', 'dune', 'echo', 'ember', 'fern', 'field', 'flame',
        'forest', 'frost', 'garden', 'glow', 'golden', 'grove', 'harbor', 'hazel', 'honey', 'island',
        'ivy', 'jade', 'jazz', 'lake', 'lemon', 'lilac', 'lily', 'lunar', 'maple', 'meadow',
        'mint', 'moon', 'oak', 'ocean', 'olive', 'orbit', 'peach', 'pearl', 'pine', 'plum',
        'pond', 'poppy', 'rain', 'river', 'robin', 'rose', 'ruby', 'sage', 'sand', 'silver',
        'sky', 'snow', 'solar', 'spark', 'spring', 'star', 'stone', 'storm', 'summer', 'sun',
        'swift', 'tide', 'tulip', 'velvet', 'violet', 'wave', 'willow', 'wind', 'winter', 'wren',
    ];

    public function generate(): string
    {
        $words = array_rand(array_flip(self::WORDS), 2);

        return $words[0].'-'.$words[1].'-'.random_int(10, 99);
    }
}
