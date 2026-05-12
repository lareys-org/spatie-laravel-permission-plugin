# Changelog

All notable changes to `laxa/spatie-laravel-permission-plugin` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

- Migrate the test suite into this repository so the package is testable in isolation. `composer test` runs 14 feature tests and 4 cache invalidation tests.
- Add `.github/workflows/tests.yml` — PHP 8.3 / 8.4 × Laravel 13 matrix. Requires a `COMPOSER_AUTH` repository secret to authenticate against the Laxa Satis repository.
- Bump `laxa/laxa` constraint to `^0.2`.
- Document the Laxa Satis repository as a prerequisite in README Installation.
- Refresh the README Testing section now that the suite lives here, and link "Laxa" to `https://laxa.app` in the intro.
- Bump `actions/checkout` to `v6` so CI runs on the Node.js 24 runtime.

## [1.0.0] — 2026-05-12

- First release. Wraps `spatie/laravel-permission` ^6.0.
- Register `RoleResource` and `PermissionResource` under an "Access Control" sidebar group.
- Add `RoleMultiSelectField`, `RoleSelectField`, and `PermissionMultiSelectField` for use on your User Resource. Render via Laxa's built-in select components — no frontend code in the plugin.
- `fillUsing` closures call `syncRoles()` / `syncPermissions()` after the host model is saved, safe to use on create and edit.
- Register `RolePolicy` and `PermissionPolicy` automatically; gated by Spatie permissions.
- Flush Spatie's permission cache on Role and Permission create / update / delete.
- Auto-discover the host application's User Resource via `Laxa::resources()->forModel()`.
- Fluent customization API: `->useRoleResource()`, `->usePermissionResource()`, `->useRolePolicy()`, `->usePermissionPolicy()`, `->forUserResource()`.
- Multi-guard support — Guard Name field appears automatically when multiple guards are configured.

### Known limitations

- `MorphToManyField` on the Role / Permission edit pages is detail-only. Assign users to a role from the User edit page using `RoleMultiSelectField` instead.

[Unreleased]: https://github.com/lareys-org/spatie-laravel-permission-plugin/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/lareys-org/spatie-laravel-permission-plugin/releases/tag/v1.0.0
