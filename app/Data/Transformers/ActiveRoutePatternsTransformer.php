<?php

namespace App\Data\Transformers;

use Illuminate\Routing\Route;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * For use with laravel-data, when wanting to convert an array of
 * routes/route patterns to a boolean, where:
 *
 * - true - if one or more route patterns match the current route
 * - false - if no route patterns match the current route
 *
 * Usage:
 * #[WithTransformer(ActiveRoutePatternsTransformer::class)]
 */
class ActiveRoutePatternsTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): bool
    {
        $currentRoute = request()->route();

        // If there are no route patterns, or thee is no current route
        // then we can return early
        if (! is_array($value) || ! $currentRoute instanceof Route) {
            return false;
        }

        return $currentRoute->named($value);
    }
}
