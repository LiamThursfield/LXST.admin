<?php

namespace App\Data\Transformers;

use App\Services\Industry\Terminology;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * For use with laravel-data, when an attribute holds a translation key (e.g. 'navigation.dashboard')
 * that should be translated during transformation, filling in the tenant's words such as :Services.
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

        if (! is_string($translated)) {
            return $value;
        }

        // Most lines have no placeholders, so only look up the tenant's words when one does
        return str_contains($translated, ':') ? app(Terminology::class)->translate($value) : $translated;
    }
}
