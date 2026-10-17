<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TenantTestCase;
use Tests\TestCase;
use Tests\Traits\CreatesUserWithPermissionsTrait;
use Tests\Traits\RefreshDatabaseWithTenantTrait;

/*
|--------------------------------------------------------------------------
| Test Cache
|--------------------------------------------------------------------------
|
| phpunit.xml gives the tests their own Redis databases and cache prefix. Parallel processes share
| those databases, so each one appends its token to the prefix before the app reads its config.
|
| Laravel's own parallel cache prefix is turned off: it only changes the config after the app has
| booted, by which point the cache store (and the permission cache) already use the old prefix.
|
*/

if (isset($_SERVER['TEST_TOKEN'])) {
    $parallelCachePrefix = getenv('CACHE_PREFIX').$_SERVER['TEST_TOKEN'].'-';

    putenv('CACHE_PREFIX='.$parallelCachePrefix);
    $_ENV['CACHE_PREFIX'] = $_SERVER['CACHE_PREFIX'] = $parallelCachePrefix;
    $_SERVER['LARAVEL_PARALLEL_TESTING_WITHOUT_CACHE'] = true;
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        flushTestCache();
    })
    ->afterEach(function (): void {
        // Tenants created by a test (e.g. for the tenants:* commands) get their own databases. The
        // tenant rows are rolled back, but the databases aren't, so drop them while the rows still
        // exist. Some tests fake tenant creation, so only drop databases that were created.
        Tenant::query()->get()->each(function (Tenant $tenant): void {
            $database = $tenant->database();

            if ($database->manager()->databaseExists($database->getName())) {
                $database->manager()->deleteDatabase($tenant);
            }
        });
    })
    ->in('Feature', 'Unit');

// Taken//adapted from previous work by https://github.com/sdsmith1981
pest()->extend(TenantTestCase::class)
    ->use(
        RefreshDatabaseWithTenantTrait::class,
        CreatesUserWithPermissionsTrait::class
    )->beforeEach(function (): void {
        flushTestCache();
        $this->withoutVite();
        Http::preventStrayRequests();
    })->in('Tenant/Feature', 'Tenant/Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Delete this process's cache keys (including tenant-prefixed ones and rate limiter hits).
 *
 * Only keys under the testing cache prefix are deleted, so even a misconfigured Redis database
 * can never lose the dev app's cache.
 */
function flushTestCache(): void
{
    $cachePrefix = (string) config('cache.prefix');

    throw_unless(
        app()->environment('testing') && str_contains($cachePrefix, '-testing-'),
        RuntimeException::class,
        "Refusing to flush cache prefix [{$cachePrefix}] outside the testing cache.",
    );

    Redis::connection(config('cache.stores.redis.connection'))->eval(
        "for _, key in ipairs(redis.call('keys', ARGV[1])) do redis.call('del', key) end",
        0,
        config('database.redis.options.prefix').$cachePrefix.'*',
    );
}
