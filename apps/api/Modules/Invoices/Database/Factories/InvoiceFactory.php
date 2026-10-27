<?php

namespace Modules\Invoices\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Invoices\Models\Invoice;
use Modules\Organization\Models\Entity;

/**
 * A standalone supplier invoice. Tests override entity_id/state per case;
 * totals stay at zero because the service computes them from the lines.
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'code' => 'INV-'.date('y').'-'.fake()->unique()->numerify('###'),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'status' => Invoice::STATUS_DRAFT,
            'supplier_name' => fake()->company(),
            'supplier_code' => null,
            'supplier_phone' => null,
            'supplier_address' => null,
            'transaction_type' => null,
            'cash_reference' => null,
            'cash_request_id' => null,
            'payment_term' => null,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'exchange_rate' => 1,
            'subtotal' => 0,
            'vat_amount' => 0,
            'vat_percent' => null,
            'discount_amount' => 0,
            'total' => 0,
            'notes' => null,
            'paid_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function recorded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Invoice::STATUS_RECORDED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Invoice::STATUS_CANCELLED,
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
