<?php

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Finder\Finder;

/**
 * Every tenant Eloquent model in app/Models (the central Tenant model aside). Found from the file
 * system, as datasets are resolved before the app boots.
 *
 * @return list<class-string<Model>>
 */
function tenantModelClasses(): array
{
    $classes = [];

    foreach ((new Finder)->files()->in(dirname(__DIR__, 4).'/app/Models')->name('*.php')->notPath('Concerns')->notPath('Settings') as $file) {
        $class = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract() && $class !== Tenant::class) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

test('every model has a factory that makes a valid record', function (string $class) {
    expect(in_array(HasFactory::class, class_uses_recursive($class), true))->toBeTrue("{$class} has no factory");

    $model = $class::factory()->create();

    expect($model->exists)->toBeTrue();
})->with(fn () => tenantModelClasses());
