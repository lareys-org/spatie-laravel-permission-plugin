<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin\Resources;

use Illuminate\Validation\Rule;
use Laxa\Enums\Icon;
use Laxa\Field\Field;
use Laxa\Field\Relations\MorphToManyField;
use Laxa\Field\Types\SelectField;
use Laxa\Http\Requests\LaxaRequest;
use Laxa\Laxa;
use Laxa\Resource\Resource;
use Laxa\Resource\ResourceDefinition;
use Laxa\SpatieLaravelPermissionPlugin\Fields\PermissionMultiSelectField;
use Spatie\Permission\PermissionRegistrar;

class RoleResource extends Resource
{
    public static function uriKey(): string
    {
        return 'roles';
    }

    public static function label(): string
    {
        return 'Roles';
    }

    public static function singularLabel(): string
    {
        return 'Role';
    }

    protected function configure(): ResourceDefinition
    {
        return ResourceDefinition::make()
            ->model(app(PermissionRegistrar::class)->getRoleClass())
            ->title('name')
            ->searchable(['id', 'name'])
            ->group('Access Control')
            ->icon(Icon::ShieldCheck);
    }

    public function fields(LaxaRequest $request): array
    {
        $guards = collect(config('auth.guards', []))->keys();
        $rolesTable = config('permission.table_names.roles', 'roles');

        return array_values(array_filter([
            Field::id()->sortable(),

            Field::text('Name', 'name')
                ->sortable()
                ->rules('required', 'string', 'max:255')
                ->creationRules(Rule::unique($rolesTable, 'name'))
                ->updateRules(fn (LaxaRequest $r) => [
                    Rule::unique($rolesTable, 'name')->ignore($r->resourceId()),
                ]),

            $guards->count() > 1
                ? SelectField::make('Guard Name', 'guard_name')
                    ->options($guards->mapWithKeys(fn ($k) => [$k => $k])->all())
                    ->default(config('permission.default_guard') ?? $guards->first())
                    ->rules('required', Rule::in($guards->all()))
                : null,

            PermissionMultiSelectField::make(),

            $this->usersField(),

            Field::dateTime('Created At', 'created_at')
                ->sortable()
                ->exceptOnForms(),
        ]));
    }

    /** Auto-discover the User resource and return a MorphToMany users field, or null. */
    protected function usersField(): ?MorphToManyField
    {
        $userModel = config('auth.providers.users.model');

        if (! $userModel) {
            return null;
        }

        $userResource = Laxa::resources()->forModel($userModel);

        if (! $userResource) {
            return null;
        }

        return MorphToManyField::make($userResource::label(), 'users', $userResource);
    }
}
