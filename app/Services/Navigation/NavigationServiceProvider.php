<?php

namespace App\Services\Navigation;

use App\Services\Navigation\Registrars\MenuRegistrar;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;

class NavigationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(NavigationRegistry::class, function ($app) {
            return new NavigationRegistry;
        });
    }

    /**
     * Bootstrap services.
     *
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $registry = $this->app->make(NavigationRegistry::class);

        // Load all the defined registrar class strings
        /** @var array<class-string<MenuRegistrar>> $registrars */
        $registrars = config('navigation.registrars', []);

        // Then load them and trigger their register method
        foreach ($registrars as $registrar) {
            ($this->app->make($registrar))->register($registry);
        }
    }
}
