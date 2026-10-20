<?php

namespace Modules\Payments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Entity;
use Modules\Payments\Models\CashRequest;

/**
 * A petty cash / advance / cash request. Tests override entity_id/state per case;
 * totals stay at zero because the service computes them from the lines.
 */
class CashRequestFactory extends Factory
{
    protected $model = CashRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'code' => 'PC-'.date('Ym').'-'.fake()->unique()->numerify('####'),
            'type' => CashRequest::TYPE_PETTY_CASH,
            'status' => CashRequest::STATUS_DRAFT,
            'campus' => fake()->randomElement(['CEN', 'MTT', 'PP']),
            'division' => fake()->randomElement(['CEN', 'MTT', 'PP']),
            'department' => fake()->randomElement(['CEN', 'MTT', 'PP']),
            'request_date' => now()->toDateString(),
            'exchange_rate' => 1,
            'mode_of_payment' => null,
            'payment_to' => null,
            'bank_account_no' => null,
            'bank_name' => null,
            'advance_request_for' => null,
            'total_usd' => 0,
            'remark' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function advance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CashRequest::TYPE_ADVANCE,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CashRequest::TYPE_CASH,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CashRequest::STATUS_APPROVED,
        ]);
    }

    public function cleared(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CashRequest::STATUS_CLEARED,
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
