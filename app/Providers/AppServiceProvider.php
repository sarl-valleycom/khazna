<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AuditContext;
use App\Support\SettingsStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AuditContext::class);
        $this->app->scoped(SettingsStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::define('view-salaries', fn (User $user): bool => $user->canViewSalaries());
        Gate::define('view-audit-log', fn (User $user): bool => $user->isOwner());
        Gate::define('view-dev-tools', fn (User $user): bool => $user->isOwner());
        Gate::define('view-forecast', fn (User $user): bool => $user->isOwner() || $user->isFinance());
        Gate::define('view-reports', fn (User $user): bool => $user->isOwner() || $user->isFinance());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

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
