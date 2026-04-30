<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromUnwantedDomains;
use Stancl\Tenancy\Middleware\ScopeSessions;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        using: function () {
            $centralDomains = config('tenancy.identification.central_domains');

            foreach ($centralDomains as $domain) {
                Route::middleware('web')
                    ->domain($domain)
                    ->as('central.')
                    ->group(base_path('routes/central/web.php'));

                Route::middleware([
                    'web',
                    'auth',
                ])->prefix('admin')
                    ->domain($domain)
                    ->as('central.admin.')
                    ->group(base_path('routes/central/admin.php'));
            }

            Route::middleware([
                'tenant',
                InitializeTenancyByDomain::class,
                PreventAccessFromUnwantedDomains::class,
            ])->group(function () {
                Route::middleware([
                    'web',
                    ScopeSessions::class,
                ])->group(base_path('routes/tenant/web.php'));

                Route::middleware([
                    'web',
                    ScopeSessions::class,
                    'auth',
                ])->prefix('admin')
                    ->as('admin.')
                    ->group(base_path('routes/tenant/admin.php'));

                Route::middleware('api')
                    ->prefix('api')
                    ->as('api.')
                    ->group(base_path('routes/tenant/api.php'));
            });
        },
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/admin');

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
