<?php

namespace Modules\Payments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Entity;
use Modules\Payments\Models\CashClearance;
use Modules\Payments\Models\CashRequest;

/**
 * A clearance against an approved cash request. Tests override entity_id/state
 * per case; totals stay at zero because the service computes them from the lines.
 */
class CashClearanceFactory extends Factory
{
    protected $model = CashClearance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'code' => 'PCC-'.date('Ym').'-'.fake()->unique()->numerify('####'),
            'status' => CashClearance::STATUS_DRAFT,
            'cash_request_id' => CashRequest::factory(),
            'clearance_date' => now()->toDateString(),
            'exchange_rate' => 1,
            'total_usd' => 0,
            'request_amount_usd' => 0,
            'balance_usd' => 0,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CashClearance::STATUS_APPROVED,
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
