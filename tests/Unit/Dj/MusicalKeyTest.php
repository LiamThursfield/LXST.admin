<?php

use App\Enums\Dj\MusicalKey;

test('keys are read from Camelot, Open Key and standard notation', function (string $notation, MusicalKey $key) {
    expect(MusicalKey::fromNotation($notation))->toBe($key);
})->with([
    'Camelot' => ['8A', MusicalKey::AMinor],
    'Camelot, lower case' => ['12b', MusicalKey::EMajor],
    'Camelot, spaced' => ['10 B', MusicalKey::DMajor],
    'Open Key minor' => ['1m', MusicalKey::AMinor],
    'Open Key major' => ['1d', MusicalKey::CMajor],
    'Open Key 6d' => ['6d', MusicalKey::BMajor],
    'minor' => ['Am', MusicalKey::AMinor],
    'major' => ['C', MusicalKey::CMajor],
    'sharp minor' => ['F#m', MusicalKey::FSharpMinor],
    'flat minor' => ['Abm', MusicalKey::AFlatMinor],
    'enharmonic sharp minor' => ['G#m', MusicalKey::AFlatMinor],
    'enharmonic flat major' => ['Gb', MusicalKey::FSharpMajor],
    'enharmonic sharp major' => ['C#', MusicalKey::DFlatMajor],
    'music symbols' => ['B♭m', MusicalKey::BFlatMinor],
    'spelt out' => ['D minor', MusicalKey::DMinor],
    'abbreviated major' => ['Eb maj', MusicalKey::EFlatMajor],
    'surrounding spaces' => ['  Em ', MusicalKey::EMinor],
]);

test('anything that is not a key reads as no key', function (?string $notation) {
    expect(MusicalKey::fromNotation($notation))->toBeNull();
})->with([
    'null' => null,
    'empty' => '',
    'unknown note' => 'H',
    'out of range Camelot' => '13A',
    'nonsense' => 'nonsense',
]);

test('every key round trips through each notation', function () {
    foreach (MusicalKey::cases() as $key) {
        expect(MusicalKey::fromNotation($key->camelot()))->toBe($key)
            ->and(MusicalKey::fromNotation($key->openKey()))->toBe($key)
            ->and(MusicalKey::fromNotation($key->standard()))->toBe($key);
    }
});

test('a key is shown in each notation', function () {
    expect(MusicalKey::FSharpMinor->camelot())->toBe('11A')
        ->and(MusicalKey::FSharpMinor->openKey())->toBe('4m')
        ->and(MusicalKey::FSharpMinor->standard())->toBe('F#m')
        ->and(MusicalKey::DFlatMajor->standard())->toBe('Db')
        ->and(MusicalKey::AMinor->label())->toBe('8A (Am)')
        ->and(MusicalKey::AMinor->isMinor())->toBeTrue()
        ->and(MusicalKey::CMajor->isMinor())->toBeFalse()
        ->and(MusicalKey::BMinor->number())->toBe(10);
});
