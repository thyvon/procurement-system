<?php

namespace Modules\Payments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Entity;
use Modules\Payments\Models\CashClearance;
use Modules\Payments\Models\CashClearanceLine;

/**
 * One priced row on a cash clearance. Amounts stay at zero because the service
 * computes them from qty, unit price, discount and VAT.
 */
class CashClearanceLineFactory extends Factory
{
    protected $model = CashClearanceLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'cash_clearance_id' => CashClearance::factory(),
            'position' => 0,
            'invoice_date' => null,
            'pr_ref' => null,
            'purchaser' => null,
            'description' => fake()->words(3, true),
            'vendor' => fake()->company(),
            'vendor_phone' => null,
            'campus' => null,
            'division' => null,
            'department' => null,
            'qty' => 0,
            'uom' => null,
            'unit_price_usd' => 0,
            'discount_usd' => 0,
            'vat_usd' => 0,
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
