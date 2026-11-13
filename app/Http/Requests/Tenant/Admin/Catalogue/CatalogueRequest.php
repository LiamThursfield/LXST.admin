<?php

namespace App\Http\Requests\Tenant\Admin\Catalogue;

use App\Enums\TenantModule;
use App\Models\Bookings\Service;
use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\ItemVariation;
use App\Models\Catalogue\Tag;
use App\Models\FileManager\File;
use App\Services\Catalogue\CatalogueContents;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating or saving a catalogue: its details, when it applies, and every section, item and
 * variation in it, as the editor sends the whole list
 *
 * @phpstan-import-type SectionData from CatalogueContents
 */
class CatalogueRequest extends FormRequest
{
    /** £100,000, in pounds */
    public const int MAX_PRICE = 100000;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $catalogue = $this->catalogue();

        return $catalogue !== null
            ? $this->user()?->can('update', $catalogue) ?? false
            : $this->user()?->can('create', Catalogue::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $catalogue = $this->catalogue();
        $image = Rule::exists(File::class, 'id')->where(fn (Builder $query) => $query->where('mime_type', 'like', 'image/%'));
        $price = ['numeric', 'decimal:0,2', 'min:0', 'max:'.self::MAX_PRICE];
        $stock = ['nullable', 'integer', 'min:0', 'max:1000000'];

        // Ids must be this catalogue's own, so nothing of another catalogue can be moved or changed
        $sectionIds = $catalogue?->sections()->pluck('id')->all() ?? [];
        $itemIds = $catalogue?->items()->pluck('catalogue_items.id')->all() ?? [];
        $variationIds = $itemIds === [] ? [] : ItemVariation::query()->whereIn('item_id', $itemIds)->pluck('id')->all();

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique(Catalogue::class, 'slug')->ignore($catalogue?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['boolean'],
            'available_days' => ['nullable', 'array'],
            'available_days.*' => ['integer', 'between:1,7', 'distinct'],
            'available_from' => ['nullable', 'date_format:H:i'],
            'available_until' => ['nullable', 'date_format:H:i', 'different:available_from'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],

            'sections' => ['present', 'array', 'max:50'],
            'sections.*.id' => ['nullable', 'integer', Rule::in($sectionIds)],
            'sections.*.name' => ['required', 'string', 'max:120'],
            'sections.*.description' => ['nullable', 'string', 'max:2000'],
            'sections.*.items' => ['present', 'array', 'max:200'],

            'sections.*.items.*.id' => ['nullable', 'integer', Rule::in($itemIds)],
            'sections.*.items.*.name' => ['required', 'string', 'max:160'],
            'sections.*.items.*.description' => ['nullable', 'string', 'max:2000'],
            'sections.*.items.*.service_id' => [
                Rule::excludeIf(! $this->hasBookings()),
                'nullable',
                'integer',
                Rule::exists(Service::class, 'id')->whereNull('deleted_at'),
            ],
            'sections.*.items.*.image_file_id' => ['nullable', 'integer', $image],
            'sections.*.items.*.price' => ['nullable', ...$price],
            'sections.*.items.*.is_available' => ['boolean'],
            'sections.*.items.*.tracks_stock' => ['boolean'],
            'sections.*.items.*.stock' => $stock,
            'sections.*.items.*.tag_ids' => ['present', 'array'],
            // Not `distinct`, which would compare every item's tags with each other's: repeats are dropped
            'sections.*.items.*.tag_ids.*' => ['integer', Rule::exists(Tag::class, 'id')],
            'sections.*.items.*.variations' => ['present', 'array', 'max:20'],
            'sections.*.items.*.variations.*.id' => ['nullable', 'integer', Rule::in($variationIds)],
            'sections.*.items.*.variations.*.name' => ['required', 'string', 'max:80'],
            'sections.*.items.*.variations.*.price' => ['required', ...$price],
            'sections.*.items.*.variations.*.stock' => $stock,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sections.*.id.in' => __('catalogue.validation.section_not_found'),
            'sections.*.items.*.id.in' => __('catalogue.validation.item_not_found'),
            'sections.*.items.*.variations.*.id.in' => __('catalogue.validation.item_not_found'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        /** @var array<string, string> */
        return __('catalogue.attributes');
    }

    /**
     * The catalogue's own details, with times as the database stores them
     *
     * @return array<string, mixed>
     */
    public function catalogueAttributes(): array
    {
        /** @var list<int|string>|null $days */
        $days = $this->validated('available_days');
        $days = array_map('intval', $days ?? []);
        sort($days);

        return [
            'name' => $this->string('name')->trim()->toString(),
            'slug' => $this->filled('slug') ? $this->string('slug')->toString() : null,
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'is_published' => $this->boolean('is_published'),
            'available_days' => $days === [] ? null : $days,
            'available_from' => $this->filled('available_from') ? $this->string('available_from')->toString().':00' : null,
            'available_until' => $this->filled('available_until') ? $this->string('available_until')->toString().':00' : null,
            'starts_on' => $this->filled('starts_on') ? $this->string('starts_on')->toString() : null,
            'ends_on' => $this->filled('ends_on') ? $this->string('ends_on')->toString() : null,
        ];
    }

    /**
     * Every section, item and variation, with prices in minor units
     *
     * @return list<SectionData>
     */
    public function contents(): array
    {
        /** @var list<array<string, mixed>> $sections */
        $sections = $this->validated('sections');

        return array_map(fn (array $section) => [
            'id' => isset($section['id']) ? (int) $section['id'] : null,
            'name' => trim((string) $section['name']),
            'description' => filled($section['description'] ?? null) ? (string) $section['description'] : null,
            'items' => array_values(array_map(fn (array $item) => $this->itemData($item), $section['items'] ?? [])),
        ], $sections);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id: int|null, name: string, description: string|null, service_id: int|null, image_file_id: int|null, price: int|null, is_available: bool, tracks_stock: bool, stock: int|null, tag_ids: list<int>, variations: list<array{id: int|null, name: string, price: int, stock: int|null}>}
     */
    protected function itemData(array $item): array
    {
        /** @var list<array<string, mixed>> $variations */
        $variations = $item['variations'] ?? [];
        /** @var list<int|string> $tagIds */
        $tagIds = $item['tag_ids'] ?? [];

        return [
            'id' => isset($item['id']) ? (int) $item['id'] : null,
            'name' => trim((string) $item['name']),
            'description' => filled($item['description'] ?? null) ? (string) $item['description'] : null,
            'service_id' => isset($item['service_id']) ? (int) $item['service_id'] : null,
            'image_file_id' => isset($item['image_file_id']) ? (int) $item['image_file_id'] : null,
            'price' => isset($item['price']) && $item['price'] !== '' ? $this->toMinorUnits($item['price']) : null,
            'is_available' => (bool) ($item['is_available'] ?? true),
            'tracks_stock' => (bool) ($item['tracks_stock'] ?? false),
            'stock' => isset($item['stock']) ? (int) $item['stock'] : null,
            'tag_ids' => array_values(array_unique(array_map('intval', $tagIds))),
            'variations' => array_map(fn (array $variation) => [
                'id' => isset($variation['id']) ? (int) $variation['id'] : null,
                'name' => trim((string) $variation['name']),
                'price' => $this->toMinorUnits($variation['price']),
                'stock' => isset($variation['stock']) ? (int) $variation['stock'] : null,
            ], $variations),
        ];
    }

    protected function catalogue(): ?Catalogue
    {
        $catalogue = $this->route('catalogue');

        return $catalogue instanceof Catalogue ? $catalogue : null;
    }

    protected function hasBookings(): bool
    {
        return tenant()?->hasModule(TenantModule::Bookings) ?? false;
    }

    /**
     * Pounds to pence, through round() as 19.99 * 100 is just under 1999 as a float
     */
    protected function toMinorUnits(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
