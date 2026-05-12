<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin\Fields;

use Laxa\Field\Types\MultiSelectField;
use Laxa\Http\Requests\LaxaRequest;
use Spatie\Permission\PermissionRegistrar;

/**
 * Multi-select field for Spatie Permissions.
 *
 * Use on the Role resource (assign permissions to a role) or on the User
 * resource (assign direct permissions to a user). Calls Spatie's
 * `syncPermissions()` after the model is saved.
 */
class PermissionMultiSelectField extends MultiSelectField
{
    public function __construct(string $name = 'Permissions', ?string $attribute = 'permissions')
    {
        parent::__construct($name, $attribute);

        $permissionClass = app(PermissionRegistrar::class)->getPermissionClass();

        $this->options(static fn (): array => $permissionClass::query()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all());

        $this->resolveUsing(static function (mixed $resource): array {
            if (! is_object($resource) || ! method_exists($resource, 'getAllPermissions')) {
                return [];
            }

            return $resource->permissions->pluck('name')->all();
        });

        $this->fillUsing(static function (LaxaRequest $request, object $model, string $attr): ?callable {
            if (! $request->exists($attr)) {
                return null;
            }

            $value = $request->input($attr);
            $names = is_string($value) ? json_decode($value, true) : $value;
            $names = is_array($names) ? array_values(array_filter($names, fn ($v) => $v !== '' && $v !== null)) : [];

            return static fn () => $model->syncPermissions($names);
        });

        $this->showOnIndex(false)
            ->showOnDetail(true);
    }
}
