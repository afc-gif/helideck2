<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\User;
use App\Policies\OrganizationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);
        Gate::define('view-platform-roles', fn (User $user): bool => $user->isSuperAdmin());
        Gate::policy(Organization::class, OrganizationPolicy::class);

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::transliterate(Str::lower($request->string('email')->toString()));

            return Limit::perMinute(5)->by(hash('sha256', $email.'|'.$request->ip()));
        });

        RateLimiter::for('registration', function (Request $request): Limit {
            return Limit::perMinute(5)->by('registration|'.$request->ip());
        });
    }
}
