<?php

namespace Modules\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Division;
use Modules\Organization\Models\Entity;

/**
 * Auto-generated factories per project rule. Resolves the tenant the same
 * way the invoice factories do so standalone factory runs still work.
 */
class DivisionFactory extends Factory
{
    protected $model = Division::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'name' => fake()->unique()->words(2, true),
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
