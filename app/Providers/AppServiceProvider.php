<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    /**
     * Configure the rate limiters used by the application's routes.
     */
    protected function configureRateLimiting(): void
    {
        // The public tenant API, limited per tenant and IP address
        RateLimiter::for('tenant-api', fn (Request $request): Limit => Limit::perMinute((int) config('api.rate_limit_per_minute'))
            ->by(tenant()?->getTenantKey().'|'.$request->ip())
        );

        // Submitting a CRM form, which is stricter than the rest of the API
        RateLimiter::for('form-submissions', fn (Request $request): Limit => Limit::perMinute((int) config('api.form_submissions.rate_limit_per_minute'))
            ->by(tenant()?->getTenantKey().'|'.$request->ip())
        );

        // Recording page views, per visitor: sites calling from their server send the visitor's IP
        RateLimiter::for('page-views', fn (Request $request): Limit => Limit::perMinute((int) config('api.page_views.rate_limit_per_minute'))
            ->by(tenant()?->getTenantKey().'|'.($request->input('ip_address') ?: $request->ip()))
        );
    }
}
