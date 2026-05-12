<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laxa\Laxa;
use Laxa\Plugin\Plugin;
use Laxa\SpatieLaravelPermissionPlugin\Policies\PermissionPolicy;
use Laxa\SpatieLaravelPermissionPlugin\Policies\RolePolicy;
use Laxa\SpatieLaravelPermissionPlugin\Resources\PermissionResource;
use Laxa\SpatieLaravelPermissionPlugin\Resources\RoleResource;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class SpatieLaravelPermissionPlugin extends Plugin
{
    /** @var class-string */
    protected string $roleResource = RoleResource::class;

    /** @var class-string */
    protected string $permissionResource = PermissionResource::class;

    /** @var class-string */
    protected string $rolePolicy = RolePolicy::class;

    /** @var class-string */
    protected string $permissionPolicy = PermissionPolicy::class;

    public function name(): string
    {
        return 'Access Control';
    }

    public function menu(Request $request): array
    {
        return [];
    }

    public function boot(Request $request): void
    {
        if (! class_exists(PermissionRegistrar::class)) {
            throw new RuntimeException(
                'spatie/laravel-permission is required. Run: composer require spatie/laravel-permission'
            );
        }

        Laxa::registerResources($this->roleResource, $this->permissionResource);

        $registrar = app(PermissionRegistrar::class);
        Gate::policy($registrar->getRoleClass(), $this->rolePolicy);
        Gate::policy($registrar->getPermissionClass(), $this->permissionPolicy);
    }

    /** Use a custom Role resource class. */
    public function useRoleResource(string $class): static
    {
        $this->roleResource = $class;

        return $this;
    }

    /** Use a custom Permission resource class. */
    public function usePermissionResource(string $class): static
    {
        $this->permissionResource = $class;

        return $this;
    }

    /** Use a custom Role policy class. */
    public function useRolePolicy(string $class): static
    {
        $this->rolePolicy = $class;

        return $this;
    }

    /** Use a custom Permission policy class. */
    public function usePermissionPolicy(string $class): static
    {
        $this->permissionPolicy = $class;

        return $this;
    }
}
