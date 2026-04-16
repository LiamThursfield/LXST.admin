<?php

namespace App\Services\Authorisation;

use App\Models\User;
use App\Services\Authorisation\Enums\Role;
use App\Services\Authorisation\Registrars\AuthorisationRegistrar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthorisationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(AuthorisationRegistry::class, function ($app) {
            return new AuthorisationRegistry;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // SuperAdmin bypass: passes all gates regardless of permission assignments.
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole(Role::SuperAdmin->value) ? true : null;
        });

        $registry = $this->app->make(AuthorisationRegistry::class);

        /** @var array<class-string<AuthorisationRegistrar>> $registrars */
        $registrars = config('authorisation.registrars', []);

        foreach ($registrars as $registrar) {
            ($this->app->make($registrar))->register($registry);
        }
    }
}
