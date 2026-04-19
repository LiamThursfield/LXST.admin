<?php

namespace App\Services\Navigation;

use App\Services\Navigation\Registrars\MenuRegistrar;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class NavigationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(NavigationRegistry::class, function ($app) {
            return new NavigationRegistry;
        });

        $this->app->singleton(NavigationService::class, function ($app) {
            /** @var array<class-string<MenuRegistrar>> $registrars */
            $registrars = config('navigation.registrars', []);

            return new NavigationService(
                $app->make(NavigationRegistry::class),
                $registrars
            );
        });
    }

    /**
     * @return string[]
     */
    public function provides(): array
    {
        return [
            NavigationRegistry::class,
            NavigationService::class,
        ];
    }
}
