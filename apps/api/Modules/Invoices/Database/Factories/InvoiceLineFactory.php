<?php

namespace Modules\Invoices\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Models\InvoiceLine;
use Modules\Organization\Models\Entity;

/**
 * One priced row on a supplier invoice. `line_total` mirrors the service
 * calculation so factory-built invoices read consistently with API ones.
 */
class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 20);
        $unitPrice = fake()->randomFloat(2, 1, 500);

        return [
            'entity_id' => $this->resolveEntity(),
            'invoice_id' => Invoice::factory(),
            'description' => fake()->words(3, true),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'discount' => 0,
            'line_total' => round($qty * $unitPrice, 2),
            'position' => 0,
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
