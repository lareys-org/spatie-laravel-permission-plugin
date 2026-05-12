<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin\Tests;

use Laxa\Laxa;
use Laxa\SpatieLaravelPermissionPlugin\PluginServiceProvider;
use Laxa\SpatieLaravelPermissionPlugin\SpatieLaravelPermissionPlugin;
use Laxa\SpatieLaravelPermissionPlugin\Tests\Fixtures\TestUser;
use Laxa\SpatieLaravelPermissionPlugin\Tests\Fixtures\TestUserResource;
use Laxa\Testing\TestCase as LaxaTestCase;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends LaxaTestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            PermissionServiceProvider::class,
            PluginServiceProvider::class,
        ]);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', TestUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures');
    }

    protected function setUp(): void
    {
        // Register the plugin and a stand-in user resource via a serving
        // callback BEFORE Laxa::ensureBooted() fires in the parent setUp.
        // This mimics the host application's LaxaServiceProvider, where
        // resources and plugins are registered inside Laxa::serving().
        Laxa::serving(function () {
            Laxa::registerResources(TestUserResource::class);
            Laxa::registerPlugins(new SpatieLaravelPermissionPlugin);
        });

        parent::setUp();
    }
}
