<?php

namespace App\Providers;

use App\Models\ApiToken;
use App\Models\Dj\Gig;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

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

        Sanctum::usePersonalAccessTokenModel(ApiToken::class);
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

        // Library search for song requests, per IP address
        RateLimiter::for('dj-search', fn (Request $request): Limit => Limit::perMinute((int) config('dj.api.search_per_minute'))
            ->by(tenant()?->getTenantKey().'|'.$request->ip())
        );

        // Song requests and upvotes, per visitor and gig
        $perVisitorAndGig = fn (string $limit) => function (Request $request) use ($limit): Limit {
            $gig = $request->route('gig');

            return Limit::perMinute((int) config("dj.requests.{$limit}"))
                ->by(tenant()?->getTenantKey().'|'.($gig instanceof Gig ? $gig->id : '').'|'.$request->ip());
        };
        RateLimiter::for('dj-requests', $perVisitorAndGig('rate_limit_per_minute'));
        RateLimiter::for('dj-upvotes', $perVisitorAndGig('upvotes_per_minute'));

        // Exchanging a guest pass password for a token; wrong passwords also lock out (see GuestTokenController)
        RateLimiter::for('dj-guest-token', fn (Request $request): Limit => Limit::perMinute(10)
            ->by(tenant()?->getTenantKey().'|'.$request->ip())
        );

        // Requests made with a tenant API token, per token. Middleware priority runs this before
        // `auth.tenant-token`, so the token is read from Sanctum's guard rather than the request's user
        RateLimiter::for('tenant-api-token', function (Request $request): Limit {
            $user = Auth::guard('sanctum')->user();
            $token = $user instanceof User ? $user->currentAccessToken() : null;

            return Limit::perMinute((int) config('api.tokens.rate_limit_per_minute'))
                ->by(tenant()?->getTenantKey().'|token|'.($token instanceof ApiToken ? $token->id : $request->ip()));
        });
    }
}
