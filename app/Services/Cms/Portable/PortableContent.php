<?php

namespace App\Services\Cms\Portable;

use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * The JSON that content moves between environments in. It's versioned, and uses natural keys rather
 * than database IDs, which differ between environments: templates by `type` + `slug`, fields by `slug`.
 */
class PortableContent
{
    public const string FORMAT = 'lxst-content';

    public const int VERSION = 1;

    /**
     * @param  array<array-key, array<string, mixed>>  $items
     * @return array{format: string, version: int, items: list<array<string, mixed>>}
     */
    public function wrap(array $items): array
    {
        return ['format' => self::FORMAT, 'version' => self::VERSION, 'items' => array_values($items)];
    }

    /**
     * The items in pasted JSON, which can't be trusted to be anything in particular
     *
     * @return list<mixed>
     *
     * @throws ValidationException
     */
    public function items(string $json): array
    {
        try {
            $document = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->invalid(__('cms.import.validation.invalid_json'));
        }

        if (! is_array($document) || ($document['format'] ?? null) !== self::FORMAT) {
            throw $this->invalid(__('cms.import.validation.unknown_format'));
        }

        $version = $document['version'] ?? null;

        if (! is_int($version) || $version < 1 || $version > self::VERSION) {
            throw $this->invalid(__('cms.import.validation.unsupported_version', ['max' => self::VERSION]));
        }

        if (! is_array($document['items'] ?? null) || ! array_is_list($document['items']) || $document['items'] === []) {
            throw $this->invalid(__('cms.import.validation.no_items'));
        }

        return $document['items'];
    }

    public function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['json' => $message]);
    }
}
