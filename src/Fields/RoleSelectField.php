<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin\Fields;

use Laxa\Field\Types\SelectField;
use Laxa\Http\Requests\LaxaRequest;
use Spatie\Permission\PermissionRegistrar;

/**
 * Single-select field for Spatie Roles.
 *
 * Use on the User resource when each user has at most one role.
 * Calls Spatie's `syncRoles([$role])` after the model is saved.
 */
class RoleSelectField extends SelectField
{
    public function __construct(string $name = 'Role', ?string $attribute = 'role')
    {
        parent::__construct($name, $attribute);

        $roleClass = app(PermissionRegistrar::class)->getRoleClass();

        $this->options(static fn (): array => $roleClass::query()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all());

        $this->resolveUsing(static function (mixed $resource): ?string {
            if (! is_object($resource) || ! method_exists($resource, 'getRoleNames')) {
                return null;
            }

            return $resource->getRoleNames()->first();
        });

        $this->fillUsing(static function (LaxaRequest $request, object $model, string $attr): ?callable {
            if (! $request->exists($attr)) {
                return null;
            }

            $value = $request->input($attr);
            $names = $value === null || $value === '' ? [] : [$value];

            return static fn () => $model->syncRoles($names);
        });

        $this->showOnIndex(false)
            ->showOnDetail(true);
    }
}
