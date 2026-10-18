<?php

use App\Services\Dj\Matching\TrackNormaliser;

beforeEach(function () {
    $this->normaliser = new TrackNormaliser;
});

test('DJ versions of a song share a title key', function (string $first, string $second) {
    expect($this->normaliser->titleKey($first))->toBe($this->normaliser->titleKey($second));
})->with([
    'case and punctuation' => ['HUMBLE.', 'Humble'],
    'dirty' => ['Humble (Dirty)', 'Humble'],
    'DJ pool intro' => ['Humble (DJcity Intro - Dirty)', 'Humble'],
    'extended mix' => ['SMOKE THE PAIN AWAY (Extended Mix)', 'Smoke The Pain Away'],
    'radio edit after a dash' => ["Don't You Worry Child - Radio Edit", "Don't You Worry Child"],
    'featured artist' => ['Glue (feat. Someone)', 'Glue'],
    'trailing featured artist' => ['Glue ft. Someone', 'Glue'],
    'version inside a remix bracket' => ['World Hold On (FISHER Rework - Extended Mix)', 'World Hold On (FISHER Rework)'],
    'remix with a version' => ['Messy - laszewo Remix (Intro - Dirty)', 'Messy - laszewo Remix'],
    'bracket styles' => ['Free [B-Sights Remix 2020]', 'Free (B-Sights Remix 2020)'],
]);

test('remixes and named edits are separate songs', function (string $first, string $second) {
    expect($this->normaliser->titleKey($first))->not->toBe($this->normaliser->titleKey($second));
})->with([
    'remix' => ['Family Matters (Owen Gerrard Extended Remix)', 'Family Matters'],
    'remix after a featured artist' => ['I Run Ft. Kaitlin Aragon (Mav Remix)', 'I Run'],
    'named edit' => ['Song - Owen Edit', 'Song'],
    'unnamed remix' => ['Song (Remix)', 'Song'],
    'rework' => ['World Hold On (FISHER Rework)', 'World Hold On'],
]);

test('the base title drops versions and featured artists but keeps its spelling', function (string $title, string $base) {
    expect($this->normaliser->baseTitle($title))->toBe($base);
})->with([
    ['Humble (DJcity Intro - Dirty)', 'Humble'],
    ['I Run Ft. Kaitlin Aragon (Mav Remix)', 'I Run (Mav Remix)'],
    ['Messy - laszewo Remix (Dirty)', 'Messy - laszewo Remix'],
    ['World Hold On (FISHER Rework - Extended Mix)', 'World Hold On (FISHER Rework)'],
    // Never reduced to nothing
    ['(Intro)', '(Intro)'],
]);

test('artist keys ignore case, punctuation, featured artists and a leading "the"', function (string $first, string $second) {
    expect($this->normaliser->artistKey($first))->toBe($this->normaliser->artistKey($second));
})->with([
    ['JAY-Z ft. Beyoncé', 'Jay Z'],
    ['The Weeknd', 'Weeknd'],
    ['Tion Wayne feat. Aitch', 'tion wayne'],
]);

test('numbered series only differ in their numbers', function () {
    expect($this->normaliser->differOnlyInNumbers('gunshots 1', 'gunshots 2'))->toBeTrue()
        ->and($this->normaliser->differOnlyInNumbers('afro house 2024', 'afro house 2026'))->toBeTrue()
        ->and($this->normaliser->differOnlyInNumbers('weeknd', 'weekend'))->toBeFalse()
        ->and($this->normaliser->differOnlyInNumbers('same 1', 'same 1'))->toBeFalse();
});

test('similarity is 1 for equal keys and lower the more they differ', function () {
    expect($this->normaliser->similarity('justin timberlake', 'justin timberlake'))->toBe(1.0)
        ->and($this->normaliser->similarity('justin timberlake', 'juston timberlake'))->toBeGreaterThan(0.9)
        ->and($this->normaliser->similarity('drake', 'bicep'))->toBeLessThan(0.5);
});
