<?php

namespace Modules\Payments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Entity;
use Modules\Payments\Models\AdvanceLiquidation;
use Modules\Payments\Models\AdvanceLiquidationLine;

/**
 * One line on an advance liquidation. Amounts stay at zero because the service
 * computes them from the exchange rate.
 */
class AdvanceLiquidationLineFactory extends Factory
{
    protected $model = AdvanceLiquidationLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'advance_liquidation_id' => AdvanceLiquidation::factory(),
            'position' => 0,
            'description' => fake()->words(3, true),
            'currency' => 'USD',
            'amount' => 0,
            'amount_usd' => 0,
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
