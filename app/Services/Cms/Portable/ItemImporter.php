<?php

namespace App\Services\Cms\Portable;

use App\Enums\Cms\TemplateType;
use App\Models\Cms\Template;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
     * What the item is known by, to show in the results
     *
     * @param  array<string, mixed>  $item
     */
    protected function identifier(array $item): string
    {
        return $item['slug'];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function name(array $item): string
    {
        return is_string($item['name'] ?? null) ? $item['name'] : $this->identifier($item);
    }

    /**
     * Something to save a new record with until its details have been checked, in the second pass
     *
     * @param  array<string, mixed>  $item
     */
    protected function placeholderName(array $item): string
    {
        return mb_substr($this->name($item), 0, 120);
    }

    /**
     * Whether something could be a slug, so it's safe to save while it's still to be checked
     */
    protected function validSlug(mixed $slug): bool
    {
        return is_string($slug) && $slug !== '' && strlen($slug) <= 120;
    }

    /**
     * The key of the template something refers to
     */
    protected function templateKey(mixed $reference): ?string
    {
        return is_array($reference) && is_string($reference['type'] ?? null) && is_string($reference['slug'] ?? null)
            ? "template/{$reference['type']}/{$reference['slug']}"
            : null;
    }

    protected function referencedTemplate(mixed $reference): ?Template
    {
        $type = is_array($reference) && is_string($reference['type'] ?? null) ? TemplateType::tryFrom($reference['type']) : null;

        return $type !== null && is_string($reference['slug'] ?? null)
            ? Template::query()->with('fields')->where('type', $type)->where('slug', $reference['slug'])->first()
            : null;
    }

    protected function missingTemplateMessage(mixed $reference): string
    {
        return (string) __('cms.import.validation.item_template_missing', ['template' => Str::after($this->templateKey($reference) ?? '?', 'template/')]);
    }

    /**
     * What to say about references that aren't here: an optional one is left empty with a warning,
     * and a required one stops the item
     *
     * @param  list<array{field: string, required: bool, kind: string, reference: string}>  $missing
     * @return array{errors: list<string>, warnings: list<string>}
     */
    protected function missingOutcome(array $missing): array
    {
        $outcome = ['errors' => [], 'warnings' => []];

        foreach ($missing as $reference) {
            $outcome[$reference['required'] ? 'errors' : 'warnings'][] = (string) __('cms.import.missing.'.($reference['required'] ? 'required' : 'optional'), [
                'what' => __("cms.import.missing.kinds.{$reference['kind']}", ['reference' => $reference['reference']]),
                'field' => $reference['field'],
            ]);
        }

        return $outcome;
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
     * The first pass: saves what the item is, so everything else can refer to it. Anything that could
     * refer to other items waits for fill().
     *
     * @param  array<string, mixed>  $item
     * @param  TModel|null  $existing
     * @return SaveOutcome
     */
    abstract protected function save(array $item, ?Model $existing): array;

    /**
     * The second pass, once every item's record exists: fills in what refers to other items, through
     * the same rules as the admin
     *
     * @param  array<string, mixed>  $item
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public function fill(array $item): array
    {
        return ['errors' => [], 'warnings' => []];
    }

    /**
     * Anything to know about a new item
     *
     * @return list<string>
     */
    protected function createNotes(): array
    {
        return [];
    }

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
            'slug' => $this->identifier($item),
            'name' => $this->name($item),
            'action' => match (true) {
                $existing === null => self::ACTION_CREATE,
                $isUnchanged => self::ACTION_UNCHANGED,
                $overwrite => self::ACTION_OVERWRITE,
                default => self::ACTION_SKIP,
            },
            'can_overwrite' => $canOverwrite,
            'changes' => $canOverwrite ? $this->changes($existing, $item) : ($existing === null ? $this->createNotes() : []),
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
