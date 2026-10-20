<?php

namespace Modules\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Entity;
use Modules\Products\Services\CodeGenerationService;

/**
 * Auto-generated factories per project rule. Resolves the tenant the same
 * way the invoice factories do so standalone factory runs still work.
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entityId = $this->resolveEntity();
        $name = fake()->unique()->words(2, true);

        return [
            'entity_id' => $entityId,
            'name' => $name,
            'short_code' => app(CodeGenerationService::class)->nextShortCode($name, Department::class, $entityId),
            'is_active' => true,
        ];
    }

    private function resolveEntity(): string
    {
        return (Entity::query()->first()
            ?? Entity::query()->firstOrCreate(
                ['code' => 'MAIN'],
                ['name' => 'Main Organization', 'timezone' => 'UTC', 'locale' => 'en'],
            ))->getKey();
    }
}
