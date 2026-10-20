<?php

namespace Modules\Payments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Entity;
use Modules\Payments\Models\AdvanceLiquidation;
use Modules\Payments\Models\CashRequest;

/**
 * A liquidation of an advance. Tests override entity_id/state per case;
 * totals stay at zero because the service computes them from the lines.
 */
class AdvanceLiquidationFactory extends Factory
{
    protected $model = AdvanceLiquidation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'code' => 'LAD-'.date('Ym').'-'.fake()->unique()->numerify('####'),
            'status' => AdvanceLiquidation::STATUS_DRAFT,
            'cash_request_id' => CashRequest::factory(),
            'liquidation_date' => now()->toDateString(),
            'exchange_rate' => 1,
            'campus' => fake()->randomElement(['CEN', 'MTT', 'PP']),
            'division' => fake()->randomElement(['CEN', 'MTT', 'PP']),
            'department' => fake()->randomElement(['CEN', 'MTT', 'PP']),
            'total_actual_expense_usd' => 0,
            'total_cash_advance_usd' => 0,
            'remaining_cash_usd' => 0,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdvanceLiquidation::STATUS_APPROVED,
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
