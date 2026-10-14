<?php

namespace Modules\Organization\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Organization\Models\Campus;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Division;
use Modules\Organization\Models\Entity;

/**
 * Minimal demo org structure per entity: one campus, one division and two
 * departments (linked to the division) when the entity has none yet.
 */
class OrganizationDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Entity::query()->each(function (Entity $entity): void {
            if (Campus::query()->whereBelongsTo($entity)->exists()) {
                return;
            }

            Campus::factory()->create([
                'entity_id' => $entity->getKey(),
                'name' => 'Main Campus',
            ]);

            $division = Division::factory()->create([
                'entity_id' => $entity->getKey(),
                'name' => 'Administration',
            ]);

            $departments = Department::factory()->count(2)->create([
                'entity_id' => $entity->getKey(),
            ]);

            $division->departments()->sync($departments->pluck('id'));
        });
    }
}
