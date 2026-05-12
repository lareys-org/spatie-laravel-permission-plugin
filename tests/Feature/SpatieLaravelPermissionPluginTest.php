<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laxa\Http\Requests\LaxaRequest;
use Laxa\Laxa;
use Laxa\SpatieLaravelPermissionPlugin\Fields\PermissionMultiSelectField;
use Laxa\SpatieLaravelPermissionPlugin\Fields\RoleMultiSelectField;
use Laxa\SpatieLaravelPermissionPlugin\Fields\RoleSelectField;
use Laxa\SpatieLaravelPermissionPlugin\Policies\PermissionPolicy;
use Laxa\SpatieLaravelPermissionPlugin\Policies\RolePolicy;
use Laxa\SpatieLaravelPermissionPlugin\Resources\PermissionResource;
use Laxa\SpatieLaravelPermissionPlugin\Resources\RoleResource;
use Laxa\SpatieLaravelPermissionPlugin\SpatieLaravelPermissionPlugin;
use Laxa\SpatieLaravelPermissionPlugin\Tests\Fixtures\TestUser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('registers the plugin via the test case', function () {
    expect(Laxa::plugins()->all()->count())->toBe(1)
        ->and(Laxa::plugins()->find('spatie-laravel-permission-plugin'))
        ->toBeInstanceOf(SpatieLaravelPermissionPlugin::class);
});

it('registers RoleResource and PermissionResource during plugin boot', function () {
    expect(Laxa::resources()->forKey('roles'))->toBe(RoleResource::class)
        ->and(Laxa::resources()->forKey('permissions'))->toBe(PermissionResource::class);
});

it('registers default policies during plugin boot', function () {
    $registrar = app(PermissionRegistrar::class);

    expect(Gate::getPolicyFor($registrar->getRoleClass()))->toBeInstanceOf(RolePolicy::class)
        ->and(Gate::getPolicyFor($registrar->getPermissionClass()))->toBeInstanceOf(PermissionPolicy::class);
});

it('binds RoleResource to the Spatie Role model in the registry', function () {
    $roleClass = app(PermissionRegistrar::class)->getRoleClass();

    expect(Laxa::resources()->forModel($roleClass))->toBe(RoleResource::class);
});

it('auto-discovers the User resource on Role/Permission resources', function () {
    $userResource = Laxa::resources()->forModel(TestUser::class);

    expect($userResource)->not->toBeNull();
});

it('RoleResource exposes the expected fields when single guard is configured', function () {
    config(['auth.guards' => ['web' => config('auth.guards.web')]]);

    $resource = new RoleResource(new (app(PermissionRegistrar::class)->getRoleClass()));
    $request = LaxaRequest::createFromRequest(request());
    $fields = $resource->fields($request);

    $names = array_map(fn ($f) => $f->name, $fields);

    expect($names)->toContain('ID', 'Name', 'Permissions', 'Created At')
        ->and($names)->not->toContain('Guard Name');
});

it('RoleResource shows Guard Name when multiple guards are configured', function () {
    config(['auth.guards' => [
        'web' => config('auth.guards.web'),
        'api' => ['driver' => 'token', 'provider' => 'users'],
    ]]);

    $resource = new RoleResource(new (app(PermissionRegistrar::class)->getRoleClass()));
    $request = LaxaRequest::createFromRequest(request());
    $fields = $resource->fields($request);

    $names = array_map(fn ($f) => $f->name, $fields);

    expect($names)->toContain('Guard Name');
});

it('PermissionMultiSelectField uses the multi-select-field component', function () {
    expect((new PermissionMultiSelectField)->component())->toBe('multi-select-field');
});

it('RoleMultiSelectField uses the multi-select-field component', function () {
    expect((new RoleMultiSelectField)->component())->toBe('multi-select-field');
});

it('RoleSelectField uses the select-field component', function () {
    expect((new RoleSelectField)->component())->toBe('select-field');
});

it('PermissionMultiSelectField returns a deferred closure that calls syncPermissions', function () {
    $field = new PermissionMultiSelectField;

    $request = LaxaRequest::createFromRequest(
        Request::create('/', 'POST', ['permissions' => '["users.view","users.create"]'])
    );

    $model = Mockery::mock();
    $model->shouldReceive('syncPermissions')
        ->once()
        ->with(['users.view', 'users.create']);

    $callback = $field->fill($request, $model);

    expect($callback)->toBeCallable();
    $callback();
});

it('RoleMultiSelectField returns a deferred closure that calls syncRoles', function () {
    $field = new RoleMultiSelectField;

    $request = LaxaRequest::createFromRequest(
        Request::create('/', 'POST', ['roles' => '["admin","editor"]'])
    );

    $model = Mockery::mock();
    $model->shouldReceive('syncRoles')
        ->once()
        ->with(['admin', 'editor']);

    $callback = $field->fill($request, $model);

    expect($callback)->toBeCallable();
    $callback();
});

it('RoleSelectField returns a deferred closure that calls syncRoles with single role', function () {
    $field = new RoleSelectField;

    $request = LaxaRequest::createFromRequest(
        Request::create('/', 'POST', ['role' => 'admin'])
    );

    $model = Mockery::mock();
    $model->shouldReceive('syncRoles')
        ->once()
        ->with(['admin']);

    $callback = $field->fill($request, $model);

    expect($callback)->toBeCallable();
    $callback();
});

it('RoleSelectField returns a deferred closure that syncs empty when null', function () {
    $field = new RoleSelectField;

    $request = LaxaRequest::createFromRequest(
        Request::create('/', 'POST', ['role' => ''])
    );

    $model = Mockery::mock();
    $model->shouldReceive('syncRoles')
        ->once()
        ->with([]);

    $callback = $field->fill($request, $model);

    expect($callback)->toBeCallable();
    $callback();
});

describe('cache invalidation', function () {
    /**
     * Spatie's PermissionRegistrar caches permissions on first read.
     * The listener fires forgetCachedPermissions(), which clears the
     * cache key. We assert the key is forgotten after each write.
     */
    function permissionCacheKey(): string
    {
        return config('permission.cache.key', 'spatie.permission.cache');
    }

    it('flushes Spatie permission cache when a role is saved', function () {
        app(PermissionRegistrar::class)->getPermissions();
        expect(cache()->has(permissionCacheKey()))->toBeTrue();

        Role::create(['name' => 'cache-test-role', 'guard_name' => 'web']);

        expect(cache()->has(permissionCacheKey()))->toBeFalse();
    });

    it('flushes Spatie permission cache when a role is deleted', function () {
        $role = Role::create(['name' => 'cache-delete-test', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->getPermissions();
        expect(cache()->has(permissionCacheKey()))->toBeTrue();

        $role->delete();

        expect(cache()->has(permissionCacheKey()))->toBeFalse();
    });

    it('flushes Spatie permission cache when a permission is saved', function () {
        app(PermissionRegistrar::class)->getPermissions();
        expect(cache()->has(permissionCacheKey()))->toBeTrue();

        Permission::create(['name' => 'cache-test-perm', 'guard_name' => 'web']);

        expect(cache()->has(permissionCacheKey()))->toBeFalse();
    });

    it('flushes Spatie permission cache when a permission is deleted', function () {
        $permission = Permission::create(['name' => 'cache-delete-perm', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->getPermissions();
        expect(cache()->has(permissionCacheKey()))->toBeTrue();

        $permission->delete();

        expect(cache()->has(permissionCacheKey()))->toBeFalse();
    });
});
