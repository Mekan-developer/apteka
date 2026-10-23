<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        $this->configureGates();
        $this->configureRateLimiting();
    }

    /**
     * The public search is open to anyone, so one visitor may not flood it.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('search', fn (Request $request): Limit => Limit::perMinute(60)->by($request->ip()));
    }

    /**
     * Who may do what. Every account is an admin; deactivated ones are signed out by EnsureUserIsActive.
     */
    protected function configureGates(): void
    {
        Gate::define('manage-prices', fn (User $user): bool => in_array($user->role, [UserRole::SuperAdmin, UserRole::Admin], true));

        Gate::define('manage-admins', fn (User $user): bool => $user->isSuperAdmin());

        // A super admin manages admins only: never their own account or another super admin.
        Gate::define('manage-admin', fn (User $user, User $admin): bool => $user->isSuperAdmin() && $admin->role === UserRole::Admin);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction());

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
            : null,
        );
    }
}
