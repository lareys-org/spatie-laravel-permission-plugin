# Laxa Spatie Laravel Permission Plugin

[![Latest Version on Packagist](https://img.shields.io/packagist/v/laxa/spatie-laravel-permission-plugin.svg?style=flat-square)](https://packagist.org/packages/laxa/spatie-laravel-permission-plugin)
[![Total Downloads](https://img.shields.io/packagist/dt/laxa/spatie-laravel-permission-plugin.svg?style=flat-square)](https://packagist.org/packages/laxa/spatie-laravel-permission-plugin)
[![License](https://img.shields.io/packagist/l/laxa/spatie-laravel-permission-plugin.svg?style=flat-square)](LICENSE.md)

A [Laxa](https://laxa.app) plugin that wraps [spatie/laravel-permission](https://github.com/spatie/laravel-permission), giving you ready-made Role and Permission resources, custom multi-select fields for assigning permissions/roles, and Spatie cache invalidation hooked into the admin UI.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Assigning roles and permissions to users](#assigning-roles-and-permissions-to-users)
- [Customization](#customization)
- [Multi-guard support](#multi-guard-support)
- [Cache invalidation](#cache-invalidation)
- [Testing](#testing)
- [Known limitations](#known-limitations)
- [Changelog](#changelog)
- [License](#license)

## Requirements

- PHP ^8.3
- Laxa ^0.2
- spatie/laravel-permission ^6.0

If you're on an older version of `spatie/laravel-permission`, upgrade first — see [Spatie's upgrade guide](https://spatie.be/docs/laravel-permission/v6/installation-laravel).

## Installation

This plugin depends on `laxa/laxa`, which is distributed via a private Satis repository. Add the Laxa repository to your application's `composer.json` first (if you haven't already as part of your Laxa setup):

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://laxa.app"
    }
]
```

Or via the CLI:

```bash
composer config repositories.laxa '{"type": "composer", "url": "https://laxa.app"}' --file composer.json
```

Then install the plugin:

```bash
composer require laxa/spatie-laravel-permission-plugin
```

Publish and run the Spatie migrations:

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations"
php artisan migrate
```

Register the plugin in `app/Providers/LaxaServiceProvider.php`:

```php
use Laxa\Laxa;
use Laxa\SpatieLaravelPermissionPlugin\SpatieLaravelPermissionPlugin;

protected function registerPlugins(): void
{
    Laxa::registerPlugins(
        new SpatieLaravelPermissionPlugin,
    );
}
```

Add the `HasRoles` trait to your `App\Models\User`:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    // ...
}
```

That's it. Navigate to `/laxa/resources/roles` to start managing roles.

## Assigning roles and permissions to users

The plugin ships three custom fields for use on **your** User resource. Add them to `app/Laxa/Resources/User.php`:

```php
use Laxa\SpatieLaravelPermissionPlugin\Fields\PermissionMultiSelectField;
use Laxa\SpatieLaravelPermissionPlugin\Fields\RoleMultiSelectField;

public function fields(LaxaRequest $request): array
{
    return [
        // ...your existing fields
        RoleMultiSelectField::make(),
        PermissionMultiSelectField::make(),
    ];
}
```

If each user has at most one role, use the single-select variant instead:

```php
use Laxa\SpatieLaravelPermissionPlugin\Fields\RoleSelectField;

RoleSelectField::make(),
```

All three fields render as Laxa's existing multi-select / select components. They sync to Spatie's pivot tables via `syncPermissions()` / `syncRoles()` **after** the user model is saved, so they're safe to use on create as well as edit.

## Seeding initial permissions and roles

The plugin does **not** ship a permission generator yet (see Roadmap). Use a Spatie seeder pattern in your `database/seeders/`:

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

public function run(): void
{
    Permission::create(['name' => 'manage users']);
    Permission::create(['name' => 'manage projects']);

    $admin = Role::create(['name' => 'admin']);
    $admin->givePermissionTo(['manage users', 'manage projects']);
}
```

## Customization

Swap in your own Resource or Policy classes via fluent methods on the plugin:

```php
Laxa::registerPlugins(
    SpatieLaravelPermissionPlugin::make()
        ->useRoleResource(\App\Laxa\Resources\Role::class)
        ->usePermissionResource(\App\Laxa\Resources\Permission::class)
        ->useRolePolicy(\App\Policies\RolePolicy::class)
        ->usePermissionPolicy(\App\Policies\PermissionPolicy::class),
);
```

## ⚠️ Production policies

The default `RolePolicy` and `PermissionPolicy` shipped with this plugin return `true` for every method. That means **any user authenticated to Laxa can manage roles and permissions** out of the box. This is fine for local development and small admin panels, but for any real production application you should ship your own policies that check user roles or capabilities:

```php
// app/Policies/RolePolicy.php
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }
    // ...etc
}
```

Then register them via `->useRolePolicy(...)` and `->usePermissionPolicy(...)` as shown above.

## Multiple authentication guards

If your app has only the default `web` guard configured, the plugin **hides** the Guard Name field on Role and Permission forms — there's no choice to make.

If you have multiple guards (e.g. `web` + `api`), the Guard Name field appears automatically with the configured guards as options, defaulting to `config('permission.default_guard')`.

## Cache invalidation

Spatie caches permissions on first read. When admins create, edit, or delete Roles/Permissions via the UI, the plugin automatically flushes Spatie's cache via Eloquent `saved` and `deleted` model events. Pivot changes from the plugin's custom fields go through Spatie's `syncPermissions()` / `syncRoles()` methods, which flush the cache themselves.

## Testing

```bash
composer test
```

Runs 14 feature tests covering plugin registration, resource binding, custom fields, and policy auto-discovery, plus 4 integration tests for cache invalidation on Role/Permission CRUD. CI runs the suite on PHP 8.3 and 8.4 against Laravel 13.

A small set of Pest 4 browser tests for the dogfood path (sidebar → Roles index → User edit) lives in the Laxa reference application and isn't ported here — they require the full Laxa frontend build to render Inertia pages. Bringing them into this repo is a v1.1 candidate.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of changes.

## Known limitations

- **`MorphToManyField` is detail-only.** The "Users" relation shown on a Role's or Permission's detail page is a sub-table view — you cannot assign users to a role from the Role edit page. Manage user-role assignments from the User edit page using `RoleMultiSelectField` instead. This is a Laxa core limitation, not specific to this plugin.

- **The Spatie `roles` and `permissions` tables collide with any existing tables of the same name** in your app. If your app already has a non-Spatie roles system, you'll need to migrate it before installing this plugin.

- **No support for soft-deleting Roles or Permissions in v1.** Spatie's models don't soft-delete by default; the policy's `restore`/`forceDelete` methods exist for contract compliance only.

## Roadmap

Planned for v1.1:

- `php artisan laxa:permissions:sync` artisan command — auto-generates Spatie permissions based on registered Laxa resources (e.g., `view User`, `create User`, etc.). Have opinions about the naming convention? Open an issue.

Considered for future versions:

- Super-admin role concept with bypass policy
- Multi-tenancy support
- Permission checkbox grid alternative field for small permission sets

## License

MIT
