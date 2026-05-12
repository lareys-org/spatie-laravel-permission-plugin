<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Default permissive policy for Spatie Roles.
 *
 * Override in production via `SpatieLaravelPermissionPlugin::make()->useRolePolicy(...)`.
 */
class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(): bool
    {
        return true;
    }

    public function view(): bool
    {
        return true;
    }

    public function create(): bool
    {
        return true;
    }

    public function update(): bool
    {
        return true;
    }

    public function delete(): bool
    {
        return true;
    }

    public function restore(): bool
    {
        return true;
    }

    public function forceDelete(): bool
    {
        return true;
    }
}
