<?php

namespace Modules\Users\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public const GUARD = 'sanctum';

    /**
     * The permission vocabulary: one `view` and one `manage` ability per
     * bounded context. Single source of truth — policies derive their checks
     * from these names, FormRequests validate against them, and the Roles UI
     * renders its matrix from `GET /api/v1/permissions`.
     *
     * @var array<int, string>
     */
    public const PERMISSIONS = [
        'users.view',
        'users.manage',
        'roles.view',
        'roles.manage',
        'entities.view',
        'entities.manage',
        'products.view',
        'products.manage',
        'categories.view',
        'categories.manage',
        'brands.view',
        'brands.manage',
        'groups.view',
        'groups.manage',
        'uoms.view',
        'uoms.manage',
        'variations.view',
        'variations.manage',
    ];

    public function run(): void
    {
        static::seed();
    }

    /**
     * Idempotent baseline: creates the vocabulary, grants `admin` every
     * ability and `staff` every read. Only ever adds grants — custom
     * permissions granted after seeding are never revoked.
     */
    public static function seed(): void
    {
        $admin = Role::findOrCreate('admin', self::GUARD);
        $staff = Role::findOrCreate('staff', self::GUARD);

        $permissions = collect(self::PERMISSIONS)
            ->map(fn (string $name): Permission => Permission::findOrCreate($name, self::GUARD));

        $admin->givePermissionTo($permissions->all());

        $staff->givePermissionTo(
            $permissions
                ->filter(fn (Permission $permission): bool => str_ends_with($permission->name, '.view'))
                ->all(),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
