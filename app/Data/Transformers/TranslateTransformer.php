<?php

namespace App\Data\Transformers;

use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * For use with laravel-data, when an attribute holds a translation key (e.g. 'navigation.dashboard')
 * that should be translated during transformation.
 * Values that are not a translation key are returned unchanged.
 *
 * Usage:
 * #[WithTransformer(TranslateTransformer::class)]
 */
class TranslateTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $translated = __($value);

        return is_string($translated) ? $translated : $value;
    }
}
