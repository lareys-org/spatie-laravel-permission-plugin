<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin;

use Laxa\Plugin\Plugin;
use Laxa\SpatieLaravelPermissionPlugin\Policies\PermissionPolicy;
use Laxa\SpatieLaravelPermissionPlugin\Policies\RolePolicy;
use Laxa\SpatieLaravelPermissionPlugin\Resources\PermissionResource;
use Laxa\SpatieLaravelPermissionPlugin\Resources\RoleResource;
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

    public function requires(): array
    {
        return [PermissionRegistrar::class];
    }

    public function resources(): array
    {
        return [$this->roleResource, $this->permissionResource];
    }

    public function policies(): array
    {
        $registrar = app(PermissionRegistrar::class);

        return [
            $registrar->getRoleClass() => $this->rolePolicy,
            $registrar->getPermissionClass() => $this->permissionPolicy,
        ];
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
