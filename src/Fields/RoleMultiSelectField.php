<?php

declare(strict_types=1);

namespace Laxa\SpatieLaravelPermissionPlugin\Fields;

use Laxa\Field\Types\MultiSelectField;
use Laxa\Http\Requests\LaxaRequest;
use Spatie\Permission\PermissionRegistrar;

/**
 * Multi-select field for Spatie Roles.
 *
 * Use on the Permission resource (which roles include this permission?) or
 * on the User resource (assign roles to a user). Calls Spatie's
 * `syncRoles()` after the model is saved.
 */
class RoleMultiSelectField extends MultiSelectField
{
    public function __construct(string $name = 'Roles', ?string $attribute = 'roles')
    {
        parent::__construct($name, $attribute);

        $roleClass = app(PermissionRegistrar::class)->getRoleClass();

        $this->options(static fn (): array => $roleClass::query()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all());

        $this->resolveUsing(static function (mixed $resource): array {
            if (! is_object($resource) || ! method_exists($resource, 'getRoleNames')) {
                return [];
            }

            return $resource->getRoleNames()->all();
        });

        $this->fillUsing(static function (LaxaRequest $request, object $model, string $attr): ?callable {
            if (! $request->exists($attr)) {
                return null;
            }

            $value = $request->input($attr);
            $names = is_string($value) ? json_decode($value, true) : $value;
            $names = is_array($names) ? array_values(array_filter($names, fn ($v) => $v !== '' && $v !== null)) : [];

            return static fn () => $model->syncRoles($names);
        });

        $this->showOnIndex(false)
            ->showOnDetail(true);
    }
}
