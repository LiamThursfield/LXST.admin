<?php

namespace App\Data\Transformers;

use Illuminate\Support\Str;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * For use with laravel-data, when wanting to replace a given string with another string in a data object's
 * attribute during transformation
 *
 * Usage:
 * #[WithTransformer(StringReplaceTransformer::class, search: 'REPLACE_ME', replace: 'WITH_ME' )]
 */
class StringReplaceTransformer implements Transformer
{
    public function __construct(
        protected string $search,
        protected string $replace = '',
    ) {}

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): string
    {
        return Str::replace($this->search, $this->replace, (string) $value);
    }
}
