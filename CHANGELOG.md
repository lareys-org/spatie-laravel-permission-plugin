# Changelog

All notable changes to `laxa/spatie-laravel-permission-plugin` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Standalone test suite. 14 feature tests + 4 cache invalidation tests now live in this repo's `tests/` directory and run via `composer test` (requires Satis auth for `laxa/laxa`).
- `.github/workflows/tests.yml` — PHP 8.3 / 8.4 × Laravel 13 matrix. Requires a `COMPOSER_AUTH` repository secret to authenticate against the Satis repo.
- Documented the Laxa Satis repository as a prerequisite in README Installation.

### Changed

- Bumped `laxa/laxa` constraint from `^0.1` to `^0.2`. The v0.2.0 release brings the `Laxa\Testing\TestCase` base class this plugin's tests rely on.

### Planned for v1.1

- 4 Pest 4 browser tests for the dogfood path remain in the rexadmin host. They require the full Laxa frontend build to render Inertia pages and aren't trivially portable to a standalone testbench. v1.1 candidate.
- F-2 lazy options endpoint (see [FINDINGS.md](FINDINGS.md)).

## [1.0.0] — 2026-05-12

### Added

- First-party Laxa plugin wrapping `spatie/laravel-permission` ^6.0.
- `RoleResource` and `PermissionResource` registered via `Plugin::boot()`, grouped under "Access Control" in the sidebar.
- Custom fields for assigning roles/permissions to users:
  - `RoleMultiSelectField` — multi-select for users with multiple roles
  - `RoleSelectField` — single-select for one-role-per-user setups
  - `PermissionMultiSelectField` — direct permission assignment
- All fields render via Laxa's existing multi-select / select components — zero frontend code shipped.
- `fillUsing` closures call `syncRoles()` / `syncPermissions()` after the host model is saved, safe to use on create and edit.
- Default `RolePolicy` and `PermissionPolicy` registered automatically; gated by Spatie permissions.
- Spatie permission cache invalidation on Role/Permission create, update, and delete.
- Auto-discovery of the host application's User Resource via `Laxa::resources()->forModel()`.
- Fluent customization API:
  - `->useRoleResource(...)` / `->usePermissionResource(...)` — substitute custom resources
  - `->useRolePolicy(...)` / `->usePermissionPolicy(...)` — override default policies
  - `->forUserResource(...)` — pin a specific user resource
- Multi-guard support — Guard Name field appears automatically when multiple guards are configured.

### Known limitations

- `MorphToManyField` on the Role/Permission edit pages is detail-only (Laxa core constraint). Assign users to a role from the User edit page instead.
- F-2 from the v1 dogfood findings (lazy options resolution) is deferred to v1.1. Eager options work in production; the limitation only affects test environments without seeded Spatie tables.

[Unreleased]: https://github.com/laxa/spatie-laravel-permission-plugin/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/laxa/spatie-laravel-permission-plugin/releases/tag/v1.0.0
