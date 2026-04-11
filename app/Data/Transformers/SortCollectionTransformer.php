<?php

namespace App\Data\Transformers;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * For use with laravel-data, when wanting to ensure a Collection attribute of a data object
 * is sorted prior to transformation
 *
 * Usage:
 * #[WithTransformer(SortCollectionTransformer::class, sortBy: 'SOME_SORT_KEY', descending: false)]
 */
class SortCollectionTransformer implements Transformer
{
    public function __construct(
        protected string $sortBy,
        protected bool $descending = false
    ) {}

    /**
     * @return array<mixed>
     */
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): array
    {
        if (! $value instanceof Collection) {
            throw new InvalidArgumentException('SortCollectionTransformer can only be applied to Collection properties.');
        }

        return $value->sortBy($this->sortBy, SORT_REGULAR, $this->descending)
            ->values()
            ->toArray();
    }
}
