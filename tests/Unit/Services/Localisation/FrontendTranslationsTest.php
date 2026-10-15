<?php

use App\Services\Localisation\FrontendTranslations;

describe('forLocale', function () {
    it('returns every translation group in lang/{locale}, keyed by file name', function () {
        $translations = app(FrontendTranslations::class)->forLocale('en');

        expect($translations)->toHaveKey('common')
            ->and($translations['common']['actions']['save'])->toBe('Save');
    });

    it('includes enum labels from the enums group', function () {
        expect(app(FrontendTranslations::class)->forLocale('en')['enums']['tenant_module']['cms'])->toBe('CMS');
    });

    it('returns an empty array for a locale without a lang directory', function () {
        expect(app(FrontendTranslations::class)->forLocale('xx'))->toBe([]);
    });
});
