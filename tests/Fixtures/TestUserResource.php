<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin\Tests\Fixtures;

use Laxa\Field\Field;
use Laxa\Http\Requests\LaxaRequest;
use Laxa\Resource\Resource;
use Laxa\Resource\ResourceDefinition;

/**
 * Stand-in for the host application's User resource.
 *
 * The plugin auto-discovers the host's user resource via
 * Laxa::resources()->forModel(User::class). Standalone tests need a
 * user resource in the registry for that discovery to succeed.
 */
class TestUserResource extends Resource
{
    public static function uriKey(): string
    {
        return 'users';
    }

    protected function configure(): ResourceDefinition
    {
        return ResourceDefinition::make()->model(TestUser::class);
    }

    public function fields(LaxaRequest $request): array
    {
        return [Field::id()];
    }
}
