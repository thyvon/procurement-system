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
        'campuses.view',
        'campuses.manage',
        'divisions.view',
        'divisions.manage',
        'departments.view',
        'departments.manage',
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
        'evaluations.view',
        'evaluations.manage',
        'direct_purchase_invoices.view',
        'direct_purchase_invoices.manage',
        'pro_purchase_invoices.view',
        'pro_purchase_invoices.manage',
        'payments.view',
        'payments.manage',
        'approvals.view',
        'approvals.manage',
        'telegram.view',
        'telegram.manage',
        'settings.view',
        'settings.manage',
    ];

    /**
     * Permission names retired by the per-branch invoice split. Deleting the
     * rows cascades their role grants away with them.
     *
     * @var array<int, string>
     */
    public const RETIRED = [
        'invoices.view',
        'invoices.manage',
    ];

    public function run(): void
    {
        static::seed();
    }

    /**
     * Idempotent baseline: creates the vocabulary, grants `admin` every
     * ability and `staff` every read. Only ever adds grants — custom
     * permissions granted after seeding are never revoked — except for the
     * retired names above, which are removed outright.
     */
    public static function seed(): void
    {
        Permission::where('guard_name', self::GUARD)
            ->whereIn('name', self::RETIRED)
            ->delete();

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
