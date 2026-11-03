<?php

namespace App\Models\Cms;

use App\Models\Concerns\HasContent;
use Database\Factories\Cms\SavedComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * A named set of a component's data that's edited in one place, e.g. a Standard pricing price card.
 * Its fields come from a component template. A component field on a page or layout can use one by
 * storing a reference to it, `{"_saved_component_id": 12}`, in place of its own values. Its own
 * content can't contain references, so chains and loops can't happen.
 *
 * @property int $id
 * @property int $template_id
 * @property string $name
 * @property string $slug
 * @property-read Collection<int, Content> $content
 */
class SavedComponent extends Model
{
    use HasContent;

    /** @use HasFactory<SavedComponentFactory> */
    use HasFactory;

    /** The key a component field's value uses to refer to a saved component */
    public const string REFERENCE_KEY = '_saved_component_id';

    protected $table = 'cms_saved_components';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'name',
        'slug',
    ];

    /**
     * Whether a component field's value is a reference, even one with nothing chosen yet
     */
    public static function isReference(mixed $value): bool
    {
        return is_array($value) && array_key_exists(self::REFERENCE_KEY, $value);
    }

    /**
     * The saved component a component field's value refers to, if it does
     */
    public static function referencedId(mixed $value): ?int
    {
        return self::isReference($value) && is_numeric($value[self::REFERENCE_KEY]) ? (int) $value[self::REFERENCE_KEY] : null;
    }

    /**
     * Every saved component referred to in a stored value, including inside repeater rows
     *
     * @return array<int, int>
     */
    public static function referencedIdsIn(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $id = self::referencedId($value);

        return $id !== null ? [$id] : collect($value)->flatMap(self::referencedIdsIn(...))->unique()->values()->all();
    }

    /**
     * What a component field stores to use this saved component
     *
     * @return array{_saved_component_id: int}
     */
    public function reference(): array
    {
        return [self::REFERENCE_KEY => $this->id];
    }

    /**
     * The content rows using this saved component
     *
     * @return BelongsToMany<Content, $this>
     */
    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'cms_content_saved_component');
    }

    /**
     * Whether anything uses this saved component, so it can't be deleted or change template
     */
    public function isInUse(): bool
    {
        return $this->contents()->exists();
    }

    /**
     * The pages and layouts using this saved component, each once
     *
     * @return Collection<int, Page|Layout>
     */
    public function usedBy(): Collection
    {
        /** @var Collection<int, Page|Layout> $owners */
        $owners = $this->contents()->with('contentable')->get()
            ->map(fn (Content $content) => $content->contentable)
            ->filter()
            ->unique(fn (Model $owner) => $owner::class.$owner->getKey())
            ->values();

        return $owners;
    }
}
