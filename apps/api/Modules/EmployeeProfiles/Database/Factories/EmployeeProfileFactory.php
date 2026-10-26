<?php

namespace Modules\EmployeeProfiles\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\EmployeeProfiles\Models\EmployeeProfile;
use Modules\Organization\Models\Entity;

class EmployeeProfileFactory extends Factory
{
    protected $model = EmployeeProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'user_id' => User::factory(),
            'card_id' => null,
            'position' => fake()->jobTitle(),
            'campus_id' => null,
            'division_id' => null,
            'department_id' => null,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'is_current' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function superseded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_current' => false,
            'effective_to' => now()->subDay()->toDateString(),
        ]);
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
