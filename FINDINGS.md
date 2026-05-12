# Findings: Validating Laxa's Plugin System

> The primary deliverable of `laxa/spatie-laravel-permission-plugin` v1.
> What worked, what surfaced, and what Laxa core shipped to fix it.

This document captures what we learned by building a real, non-trivial first-party plugin on top of Laxa's plugin system. We deliberately picked Spatie Permission integration because it exercises multiple plugin-system surfaces simultaneously: Resource registration via `Plugin::boot()`, custom field types, custom policies, model-event listeners, cross-package class extension, and auto-discovery of host-app resources.

**Status:** All findings except F-2 (deferred to v1.1) have been resolved in Laxa core as part of the plugin v1 release prep. See the "Resolution" line under each finding.

## Verified preconditions

These were assumed correct during design, based on Explore agent source-code reads. All were **confirmed** in practice by booting the plugin in the rexadmin app and exercising the lifecycle via `Laxa::dispatchServingEvent()` + `Laxa::bootPlugins()` + direct introspection of registries.

| Precondition | Source evidence | Confirmed in practice |
|---|---|---|
| `Plugin::boot()` runs before `ResourceController` invocation | `laxa/src/Http/Middleware/BootPlugins.php` + middleware order in `LaxaServiceProvider.php:88-99` | ✅ Resources registered inside `boot()` appear in `Laxa::resources()` after `bootPlugins` runs |
| `ResourceRegistry::byModel` always populated for Resource subclasses | `ResourceRegistry.php:20-31` | ✅ `Laxa::resources()->forModel(Spatie\Permission\Models\Role::class)` returns our `RoleResource` |
| Custom fields can reuse existing component keys | Frontend registry dispatches purely by string | ✅ `(new PermissionMultiSelectField)->component()` returns `'multi-select-field'`; no class-identity check |
| `fillUsing` closures run **after** model save inside a transaction | `ResourceController.php:363-368, 469-474` | ✅ Tested in `it('PermissionMultiSelectField returns a deferred closure that calls syncPermissions')`; mock invoked exactly once |
| `Resource::group()` populates definition for nav grouping | `ResourceDefinition.php:137-142` | ⚠️ Group string IS populated, but auto-nav rendering required the F-7 middleware fix before plugin resources actually appeared in the sidebar |
| `null` entries in `fields()` return array are filtered silently | `SectionPresenter::flattenFields()` | ✅ Used the pattern `$cond ? Field::make() : null` for the Guard Name field with no errors |

## Newly surfaced findings

These were not anticipated. They range from "minor caveat to document" to "Laxa core should consider this."

### F-1 — `uriKey()` collisions silently overwrite

**Severity:** medium. Quality-of-life issue for plugin authors.

When two Resources share the same `uriKey()`, `ResourceRegistry::register()` performs a last-write-wins assignment: `$this->resources[$key] = $resource`. There is no warning, no exception, no log entry. In our dogfood, rexadmin's existing `App\Laxa\Resources\Role` (uriKey `roles`) was **silently replaced** by our plugin's `RoleResource` (also uriKey `roles`) when `Plugin::boot()` ran after `LaxaApplicationServiceProvider::registerResources()`.

**Reproduction:**

```php
$req = Request::create('/laxa', 'GET');
$req->setLaravelSession(app('session.store'));
app()->instance('request', $req);

Laxa::dispatchServingEvent($req);
// At this point: Laxa::resources()->forKey('roles') === App\Laxa\Resources\Role::class

Laxa::bootPlugins($req);
// Now: Laxa::resources()->forKey('roles') === Laxa\SpatieLaravelPermissionPlugin\Resources\RoleResource::class
// The original is gone, no warning was emitted.
```

**Recommended core change:** In `ResourceRegistry::register()`, detect collisions and either:

1. Throw a `ResourceUriKeyCollisionException` with a clear message naming both resource classes and the conflicting key, or
2. Log a warning at `notice` level and skip the second registration, or
3. Provide a `Laxa::resources()->overrideAllowed()` opt-in.

Option 1 is preferred — silent overwrites of namespaced state are dangerous and break the principle of least surprise.

**✅ Resolution (shipped in plugin v1 release prep):** Option 1 implemented. `Laxa\Exceptions\ResourceUriKeyCollisionException` thrown by `ResourceRegistry::register()` when a different class registers the same `uriKey`. Re-registering the same class is a no-op. Tests: `laxa/tests/Feature/FoundationTest.php` → `ResourceRegistry → throws on uriKey collision` + `re-registering the same resource class is a no-op`.

### F-2 — Eager options resolution breaks fields without DB-ready environments

**Severity:** medium. Affects testing, install-time, console contexts.

`HasFieldOptions::optionsPresenterPayload()` calls `resolveOptions()` immediately when the payload is built. For fields whose options come from a DB Closure (like our `PermissionMultiSelectField` querying `permissions` table), this fires a query during what callers may expect to be a static payload computation.

**Symptoms:**
- During testing without migrated DB: payloads throw `QueryException` with "no such table".
- During app install before migrations: any code path that touches `presenterPayload()` (e.g., a console command introspecting resources) errors.
- During tests where you assert payload shape: you need a fully seeded DB.

**Reproduction:** Run `(new PermissionMultiSelectField)->presenterPayload()` against an in-memory SQLite with no Spatie tables → `QueryException`.

**Recommended core change:** Lazy options serialization. Options should be presented as a *deferred* contract — either:

1. A separate API endpoint the frontend hits to fetch options (`/laxa-api/fields/{uriKey}/options`), like Laxa already does for relations, or
2. A try/catch around `resolveOptions()` in the payload builder that swallows DB errors and returns empty options with a logged warning.

Workaround in plugin tests: use the field's `->component()` getter instead of `presenterPayload()` for component-string assertions. Avoid `presenterPayload()` in tests without seeded data.

**⏸ Resolution (deferred to v1.1):** Full lazy-options endpoint is ~3–4 hours of PHP + Vue work touching every DB-backed field. Decided not worth blocking plugin v1 on it — the workaround above is documented, and eager resolution works in production. Tracked as a v1.1 follow-up.

### F-3 — `{{resourceId}}` placeholder syntax doesn't work in Laxa

**Severity:** low. Documentation-only for migrators from Nova.

Existing rexadmin code (`app/Laxa/Resources/Role.php:35` and `app/Laxa/Resources/User.php:71`) uses Nova's `{{resourceId}}` placeholder in unique rules:

```php
->updateRules('unique:users,email,{{resourceId}}')
```

Laxa's `Field::resolveRules()` does **not** substitute this placeholder. Rules pass through verbatim. The unique check would either succeed against literal `{{resourceId}}` (treated as a string ID — unlikely to match) or fail validation in unexpected ways.

**Reproduction:** Look at `laxa/src/Field/Field.php:1208-1232` — `resolveRules` only handles `Closure` and scalar rules, no string interpolation.

**Workaround used in this plugin:** Pass a Closure that receives the request:

```php
->updateRules(fn (LaxaRequest $r) => [
    Rule::unique($rolesTable, 'name')->ignore($r->resourceId()),
])
```

**Recommended core change:** Either implement `{{resourceId}}` substitution for migration parity with Nova (one regex in `resolveRules`), or document explicitly that the placeholder is unsupported and the Closure form is required.

**⏸ Resolution:** Documentation-only — no code change in this pass. Closure form is the recommended pattern going forward. If Nova migration support becomes a priority, the regex substitution is a one-line addition in `Field::resolveRules()`.

### F-4 — `Laxa::serving()` doesn't fire in non-HTTP contexts

**Severity:** medium. Affects CLI tools and tests.

`LaxaApplicationServiceProvider::boot()` wires `Laxa::serving(...)` callbacks that register resources, dashboards, and plugins. Those callbacks only fire when `ServeLaxa` middleware detects `Util::isLaxaRequest($request)` is true. In CLI contexts (e.g., `php artisan tinker --execute '...'`), no request is dispatched, so:

- `Laxa::plugins()->all()->count()` returns 0
- `Laxa::resources()->all()->count()` returns 0 (or only built-in resources like `ActionEventResource`)

Our tests work around this by manually constructing a request and calling `Laxa::dispatchServingEvent()` + `Laxa::bootPlugins()`. That's not obvious to a plugin author writing their first test.

**Reproduction:** Run `php artisan tinker --execute 'echo Laxa::plugins()->all()->count();'` → 0.

**Recommended core change:** Provide either:

1. A `Laxa::bootForConsole()` or `Laxa::ensureBooted()` helper that runs the full serving lifecycle without a request, or
2. A `Tests\LaxaTestCase` base class shipped with Laxa that automatically dispatches a serving event in `setUp()`.

Lacking either, every plugin's test suite re-invents the same `beforeEach()` boilerplate we wrote in `tests/Feature/SpatieLaravelPermissionPluginTest.php`.

**✅ Resolution (shipped in plugin v1 release prep):** Both delivered.
- `Laxa::ensureBooted()` runs `dispatchServingEvent` + `bootPlugins` with a synthetic `LaxaRequest`. Idempotent within a process. Resets when `flushState()` is called. Works in CLI/tinker contexts.
- `Laxa\Testing\TestCase` extends Orchestra `TestCase`, uses `InteractsWithLaxa` trait, auto-calls `ensureBooted()` in `setUp()` and `flushState()` in `tearDown()`.

Tests: `laxa/tests/Feature/EnsureBootedTest.php` (7 tests).

### F-5 — Composer scaffolder's interactive prompt is non-interruptible

**Severity:** low. Quality-of-life issue.

`php artisan laxa:plugin {vendor/name}` runs `composer update` automatically via an interactive `confirm('Update Composer packages?')` prompt. Piping `no` to stdin still ran the update — the prompt may default to "yes" or doesn't read stdin as expected.

**Reproduction:** `echo "no" | php artisan laxa:plugin foo/bar` → composer update runs anyway.

**Recommended core change:** Honor `--no-interaction` and a `--skip-composer` flag, so CI and scripted setups can patch the generated `composer.json` before installing.

**✅ Resolution (shipped in plugin v1 release prep):** Both flags added to `laxa:plugin`:
- `--skip-composer` — explicit opt-out of the composer update prompt
- `--no-interaction` (built-in Laravel flag) — suppresses the prompt and returns immediately

`MakePluginCommand::shouldRunComposerUpdate()` checks both. Tests: `laxa/tests/Feature/GeneratorCommandTest.php` → `laxa:plugin → --skip-composer skips the composer update prompt entirely` + `--no-interaction skips the composer update prompt`.

### F-6 — `MorphToManyField` is form-hidden (detail-only)

**Severity:** known limitation per design grilling Q5.

Confirmed during dogfooding. Setting `->showOnCreating(false)->showOnUpdating(false)` in `BelongsToManyField::__construct` (line 35-37) is mirrored in `MorphToManyField` (line 28-30). This means assigning users to a Role from the Role edit page isn't possible — users must be assigned roles from the User edit page using our `RoleMultiSelectField`.

This is **documented in the README** as a known limitation. Not blocking, but worth flagging that Laxa's pivot UX model differs from Nova's "attach from either side" model.

**Recommended core change:** Consider supporting an inline-select alternative renderer for `BelongsToMany`/`MorphToMany` that bypasses the sub-table for small related-record counts. (Not v1 scope; future enhancement.)

**⏸ Resolution:** Documented as a known limitation in the plugin README. No code change — this is by design in Laxa core. Future enhancement candidate.

### F-7 — Plugin-registered resources invisible in auto-nav

**Severity:** high. Hard bug — blocked plugin resources from appearing in the sidebar at all. **Fixed in Laxa core during dogfooding.**

Surfaced when the rexadmin sidebar rendered every host-app resource group (Projects, Finance, Team) but the plugin's "Access Control" group was missing, despite the plugin's resources being correctly registered, authorized, and policy-bound.

**Root cause:** The `laxa` middleware group was built as `[...config('laxa.middleware'), BootPlugins]` in `LaxaServiceProvider::registerMiddleware()`, which expands to `[web, HandleInertiaRequests, BootPlugins]`. Inertia's `Middleware::handle()` calls `share($request)` **before** `$next($request)` (see `vendor/inertiajs/inertia-laravel/src/Middleware.php`). That means `Laxa::jsonVariables()` — which builds the `resources` payload — runs while `BootPlugins` has not yet executed. Plugin-registered resources from `Plugin::boot()` exist in the registry only after `BootPlugins` runs, so they never made it into the Inertia payload, and the frontend `NavResources` component never received them.

The resources WERE correctly routable (controller-level access works because `BootPlugins` runs before the controller in the same chain), so the bug was invisible until you tried to render the sidebar.

**Reproduction (pre-fix):**

```php
// In a real HTTP request lifecycle, the order is:
// 1. ServeLaxa middleware → Laxa::dispatchServingEvent() → plugin INSTANCE registered (but boot() not called)
// 2. HandleInertiaRequests::handle() → share() → Laxa::jsonVariables() → reads Laxa::resources() (plugin resources MISSING)
// 3. BootPlugins → Laxa::bootPlugins() → Plugin::boot() → Laxa::registerResources() (TOO LATE)
// 4. Controller runs

// Verified by tinker:
$user = User::where('email', 'admin@demo.laxa.dev')->first();
auth()->login($user);
$r = Request::create('/laxa', 'GET');
Laxa::dispatchServingEvent($r);
// Skip BootPlugins here — mimics what HandleInertiaRequests sees
$resources = Laxa::resources()->authorized(LaxaRequest::createFromRequest($r))->all();
// No RoleResource / PermissionResource in $resources
```

**Fix applied:** Reorder `BootPlugins` to run **before** `HandleInertiaRequests` in the `laxa` middleware group. One-line change in `laxa/src/LaxaServiceProvider.php`:

```diff
- $router->middlewareGroup('laxa', array_merge(
-     config('laxa.middleware', ['web']),
-     [
-         \Laxa\Http\Middleware\BootPlugins::class,
-     ]
- ));
+ $router->middlewareGroup('laxa', array_merge(
+     [\Laxa\Http\Middleware\BootPlugins::class],
+     config('laxa.middleware', ['web']),
+ ));
```

After the fix, the `laxa` group expands to `[BootPlugins, web, HandleInertiaRequests]`. `BootPlugins::handle()` runs `Laxa::bootPlugins()` first, then passes control to `HandleInertiaRequests::handle()`, which now reads a fully populated resource registry in `share()`.

**Why this matters:** Without this fix, the plugin system's headline use case — "a plugin contributes new Resources to the admin panel" — silently failed at the sidebar. Resources were technically registered but invisible to users. Every future plugin author would have hit this bug.

**Validation:** After applying the fix and a hard refresh, the "Access Control" group with Roles and Permissions appears in the rexadmin sidebar alongside the host app's existing groups.

**✅ Regression guard added (plugin v1 release prep):** `laxa/tests/Feature/MiddlewareOrderTest.php` asserts that `BootPlugins` is the first middleware in the `laxa` group and that no other middleware appears before it. The test was verified to fail if the order is reversed — preventing silent regressions of F-7.

## What worked well

A few things deserve explicit praise:

- **Field extension across package boundaries is friction-free.** Our three custom fields (`PermissionMultiSelectField`, `RoleMultiSelectField`, `RoleSelectField`) all extend Laxa's built-in classes and reuse the existing React components by setting `withComponent('multi-select-field')` in the constructor. **Zero TypeScript/React code shipped in the plugin.** This is the kind of plugin ergonomic Filament gets right and Nova doesn't.

- **`Plugin::boot()` timing is correct.** Resources registered inside the per-request `boot()` method are reliably available to `ResourceController` later in the same request. No timing footguns despite a non-trivial middleware pipeline.

- **`Laxa::resources()->forModel()` reliability.** Auto-discovering the host app's User Resource via the model lookup worked the first time, with no setup required. The plugin's Role/Permission resources auto-link to the existing User resource without the user having to configure anything.

- **The fluent customization API stays out of the way.** `->useRoleResource(...)` etc. mirrors Laxa's existing fluent vocabulary (`->withFiles()`, `->prunable()`, etc.). Looks like Laxa, feels like Laxa.

- **Composer path-repo + npm workspace integration via `laxa:plugin`.** Scaffolder ran clean, autowired root `composer.json` and `package.json` workspaces, generated working `PluginServiceProvider`, `Authorize` middleware, and route file. One command, zero manual wiring.

## What we deferred / did not test

Honest list of remaining gaps:

- **F-2 lazy options endpoint** deferred to v1.1. The eager workaround works in production; the limitation only affects test environments without seeded Spatie tables.
- **F-3 `{{resourceId}}` substitution** documentation-only — Closure form is the recommended pattern.
- **Multi-guard rendering** verified at the field-shape level but not visually in the UI. Would require a multi-guard test environment.

## Conclusion

The plugin system **works**, and the dogfood drove meaningful core improvements. Seven findings surfaced; **five shipped fixes** in the v1 release prep (F-1, F-4, F-5, F-7) plus documentation (F-3, F-6); **F-2 is deferred** to v1.1.

**Plugin v1 ships with:**

- 14 unit/feature tests covering plugin registration, resource binding, custom fields, and policy auto-discovery
- 4 integration tests verifying cache invalidation on Role/Permission CRUD
- 4 Pest 4 browser tests for the dogfood path (sidebar → Roles index → Permissions index → User edit)
- CHANGELOG, LICENSE, CI workflow, polished README

**Laxa core ships with (in this release pass):**

- `ResourceUriKeyCollisionException` — no more silent overwrites (F-1)
- `Laxa::ensureBooted()` + `Laxa\Testing\TestCase` — console + test contexts boot Laxa without HTTP request boilerplate (F-4)
- `laxa:plugin --skip-composer` + `--no-interaction` honored (F-5)
- Middleware-order regression test guarding F-7 fix

**Ready for release:**

- Local prep complete (CHANGELOG, LICENSE, .github/workflows/tests.yml, polished README)
- Extraction to a standalone repo + Packagist publish is the next external step (per the original plan's Phase 6)
