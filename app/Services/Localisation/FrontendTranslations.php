<?php

namespace App\Services\Localisation;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Filesystem\Filesystem;

/**
 * Collects the translation groups in lang/{locale}/*.php so they can be shared with the frontend,
 * where the useTrans() composable reads them
 */
class FrontendTranslations
{
    public function __construct(
        protected Filesystem $files,
        protected Translator $translator,
    ) {}

    /**
     * Returns every translation group for the given locale, keyed by group (file) name
     * e.g. ['common' => ['actions' => ['save' => 'Save']]]
     *
     * @return array<string, mixed>
     */
    public function forLocale(string $locale): array
    {
        $directory = lang_path($locale);

        if (! $this->files->isDirectory($directory)) {
            return [];
        }

        $translations = [];

        foreach ($this->files->files($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $group = $file->getFilenameWithoutExtension();
            $lines = $this->translator->get($group, [], $locale);

            if (is_array($lines)) {
                $translations[$group] = $lines;
            }
        }

        ksort($translations);

        return $translations;
    }
}
