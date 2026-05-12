<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laxa\SpatieLaravelPermissionPlugin\Http\Middleware\Authorize;
use Spatie\Permission\PermissionRegistrar;

class PluginServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            $this->routes();
        });

        $this->registerCacheInvalidationListeners();
    }

    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['laxa.api', Authorize::class])
            ->prefix('laxa-api/plugins/spatie-laravel-permission-plugin')
            ->group(__DIR__.'/../routes/api.php');
    }

    /**
     * Listen for changes to Role and Permission models and flush Spatie's
     * permission cache. Spatie does not auto-flush on model CRUD; without
     * this, the admin UI would mutate the DB but stale permissions would
     * keep being served until the cache TTL expired.
     */
    protected function registerCacheInvalidationListeners(): void
    {
        if (! class_exists(PermissionRegistrar::class)) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $flush = static fn () => app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roleClass = $registrar->getRoleClass();
        $permissionClass = $registrar->getPermissionClass();

        $roleClass::saved($flush);
        $roleClass::deleted($flush);
        $permissionClass::saved($flush);
        $permissionClass::deleted($flush);
    }

    public function register(): void
    {
        //
    }
}
