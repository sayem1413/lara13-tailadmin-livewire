<?php

namespace App\Providers;

use App\Http\Middleware\EnsureApplicationIsNotInMaintenanceMode;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\Permission\Role;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Repositories\Eloquent\ActivityLog\ActivityLogRepository;
use App\Repositories\Eloquent\Category\CategoryRepository;
use App\Repositories\Eloquent\Dashboard\DashboardRepository;
use App\Repositories\Eloquent\Inventory\StockMovementRepository;
use App\Repositories\Eloquent\Media\MediaRepository;
use App\Repositories\Eloquent\Notification\NotificationPreferenceRepository;
use App\Repositories\Eloquent\Notification\NotificationRepository;
use App\Repositories\Eloquent\Permission\PermissionRepository;
use App\Repositories\Eloquent\Product\ProductRepository;
use App\Repositories\Eloquent\Role\RoleRepository;
use App\Repositories\Eloquent\Setting\SettingRepository;
use App\Repositories\Eloquent\User\UserRepository;
use App\Repositories\Interfaces\ActivityLog\ActivityLogRepositoryInterface;
use App\Repositories\Interfaces\Category\CategoryRepositoryInterface;
use App\Repositories\Interfaces\Dashboard\DashboardRepositoryInterface;
use App\Repositories\Interfaces\Inventory\StockMovementRepositoryInterface;
use App\Repositories\Interfaces\Media\MediaRepositoryInterface;
use App\Repositories\Interfaces\Notification\NotificationPreferenceRepositoryInterface;
use App\Repositories\Interfaces\Notification\NotificationRepositoryInterface;
use App\Repositories\Interfaces\Permission\PermissionRepositoryInterface;
use App\Repositories\Interfaces\Product\ProductRepositoryInterface;
use App\Repositories\Interfaces\Role\RoleRepositoryInterface;
use App\Repositories\Interfaces\Setting\SettingRepositoryInterface;
use App\Repositories\Interfaces\User\UserRepositoryInterface;
use App\Services\Lifecycle\LifecycleIntegrityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(SettingRepositoryInterface::class, SettingRepository::class);
        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(NotificationPreferenceRepositoryInterface::class, NotificationPreferenceRepository::class);
        $this->app->bind(MediaRepositoryInterface::class, MediaRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(StockMovementRepositoryInterface::class, StockMovementRepository::class);

        // Singleton: the cascading flag in LifecycleIntegrityService must
        // be shared across every guard/cascade call within one triggering
        // action's call stack (see the class docblock).
        $this->app->singleton(LifecycleIntegrityService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::before(fn (User $user, string $ability) => $user->hasRole('Super Admin') ?: null);

        Gate::policy(Role::class, RolePolicy::class);

        // Without this, a Livewire component already open in a browser tab
        // keeps accepting wire:click/wire:model requests after the acting
        // user is deactivated or the app is put into maintenance mode: the
        // package's own update endpoint only carries the "web" middleware
        // group, not the "active"/"maintenance" aliases applied to the page
        // route that rendered the component. This re-applies them to every
        // subsequent Livewire request for a route that had them.
        Livewire::addPersistentMiddleware([
            EnsureUserIsActive::class,
            EnsureApplicationIsNotInMaintenanceMode::class,
        ]);

        // Fixture tables for the Entity Lifecycle module's own test suite
        // (see docs/lifecycle-integrity.md) - kept out of the real
        // database/migrations directory since they're not part of the
        // application's actual schema. Must be registered during boot
        // (before RefreshDatabase's migrate:fresh runs), not from the
        // test case itself.
        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(base_path('tests/Fixtures/Lifecycle/migrations'));
        }
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
            : null,
        );
    }
}
