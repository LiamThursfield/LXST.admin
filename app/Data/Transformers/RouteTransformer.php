<?php

namespace App\Data\Transformers;

use Illuminate\Support\Facades\Route;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\Transformer;

/**
 * For use with laravel-data, when wanting to ensure a route attribute of a data object
 * is transformed to a url if it is a route name
 *
 * Usage:
 * #[WithTransformer(RouteTransformer::class)]
 */
class RouteTransformer implements Transformer
{
    public function transform(DataProperty $property, mixed $value, TransformationContext $context): ?string
    {
        if ($value === null) {
            return null;
        }

        return Route::has($value) ? route($value, [], false) : $value;
    }
}
