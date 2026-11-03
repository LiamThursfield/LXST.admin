<?php

namespace App\Services\Cms\Portable;

use Illuminate\Database\Eloquent\Model;

/**
 * Imports one kind of portable item: whether it's new, already here, changed or to be skipped, and
 * saving it through the same rules as the admin. Items are untrusted, so only what's expected is read.
 *
 * @phpstan-type ImportResult array{key: string, kind: string, type: string, type_label: string, slug: string, name: string, action: string, can_overwrite: bool, changes: list<string>, warnings: list<string>, errors: list<string>}
 * @phpstan-type SaveOutcome array{errors: list<string>, warnings: list<string>}
 *
 * @template TModel of Model
 */
abstract class ItemImporter
{
    public const string ACTION_CREATE = 'create';

    public const string ACTION_OVERWRITE = 'overwrite';

    public const string ACTION_SKIP = 'skip';

    public const string ACTION_UNCHANGED = 'unchanged';

    /**
     * The kind of item this imports, as in the JSON
     */
    abstract public function kind(): string;

    /**
     * What identifies the item across environments, or null if it doesn't say
     *
     * @param  array<mixed>  $item
     */
    abstract public function key(array $item): ?string;

    /**
     * The keys of other items that need importing first
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    abstract public function dependencies(array $item): array;

    /**
     * @param  array<string, mixed>  $item
     */
    abstract protected function typeLabel(array $item): string;

    /**
     * @param  array<string, mixed>  $item
     */
    protected function type(array $item): string
    {
        return $this->kind();
    }

    /**
     * What's already here with the item's key
     *
     * @param  array<string, mixed>  $item
     * @return TModel|null
     */
    abstract protected function existing(array $item): ?Model;

    /**
     * What's here, as it would be exported, to compare with the item
     *
     * @param  TModel  $existing
     * @return array<string, mixed>
     */
    abstract protected function exported(Model $existing): array;

    /**
     * Whether the signed-in user can create and overwrite this kind of item
     */
    abstract protected function userCanManage(): bool;

    /**
     * Saves the item through the same rules as the admin
     *
     * @param  array<string, mixed>  $item
     * @param  TModel|null  $existing
     * @return SaveOutcome
     */
    abstract protected function save(array $item, ?Model $existing): array;

    /**
     * Why this kind of item can't be imported here at all, if there's a reason
     */
    protected function unavailableReason(): ?string
    {
        return null;
    }

    /**
     * What overwriting would change
     *
     * @param  TModel  $existing
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function changes(Model $existing, array $item): array
    {
        return [];
    }

    /**
     * What overwriting would risk
     *
     * @param  TModel  $existing
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function overwriteWarnings(Model $existing, array $item): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return ImportResult
     */
    public function import(string $key, array $item, bool $overwrite, bool $isInLoop): array
    {
        $existing = $this->existing($item);
        $isUnchanged = $existing !== null && $this->canonical($this->exported($existing)) === $this->canonical($item);
        $canOverwrite = $existing !== null && ! $isUnchanged;

        $result = [
            'key' => $key,
            'kind' => $this->kind(),
            'type' => $this->type($item),
            'type_label' => $this->typeLabel($item),
            'slug' => $item['slug'],
            'name' => is_string($item['name'] ?? null) ? $item['name'] : $item['slug'],
            'action' => match (true) {
                $existing === null => self::ACTION_CREATE,
                $isUnchanged => self::ACTION_UNCHANGED,
                $overwrite => self::ACTION_OVERWRITE,
                default => self::ACTION_SKIP,
            },
            'can_overwrite' => $canOverwrite,
            'changes' => $canOverwrite ? $this->changes($existing, $item) : [],
            'warnings' => $canOverwrite ? $this->overwriteWarnings($existing, $item) : [],
            'errors' => [],
        ];

        if (! in_array($result['action'], [self::ACTION_CREATE, self::ACTION_OVERWRITE], true)) {
            return $result;
        }

        $problem = match (true) {
            $this->unavailableReason() !== null => $this->unavailableReason(),
            ! $this->userCanManage() => (string) __('cms.import.validation.not_allowed'),
            $isInLoop => (string) __('cms.templates.validation.nests_itself'),
            default => null,
        };

        if ($problem !== null) {
            $result['errors'] = [$problem];

            return $result;
        }

        $outcome = $this->save($item, $existing);
        $result['errors'] = $outcome['errors'];
        $result['warnings'] = [...$result['warnings'], ...$outcome['warnings']];

        return $result;
    }

    /**
     * A form of an item that can be compared, whichever environment it's from and however it's laid out
     *
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    protected function canonical(array $value): array
    {
        $canonical = [];

        foreach ($value as $key => $item) {
            $item = is_array($item) ? $this->canonical($item) : $item;

            if ($item !== null && $item !== [] && $item !== false && $item !== '') {
                $canonical[$key] = $item;
            }
        }

        if (! array_is_list($value)) {
            ksort($canonical);
        }

        return $canonical;
    }
}
