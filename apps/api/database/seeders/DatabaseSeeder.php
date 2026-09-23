<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Organization\Models\Entity;
use Modules\Users\Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $entity = Entity::query()->firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Organization',
                'timezone' => 'UTC',
                'locale' => 'en',
                'is_active' => true,
            ],
        );

        foreach (['admin', 'staff'] as $role) {
            Role::findOrCreate($role, 'sanctum');
        }

        $this->call(PermissionSeeder::class);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@procurement.local'],
            [
                'name' => 'Admin',
                'password' => Hash::make('secret-password'),
                'entity_id' => $entity->getKey(),
            ],
        );

        $admin->assignRole('admin');
    }
}
