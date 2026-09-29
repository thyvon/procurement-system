<?php

namespace Modules\Telegram\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Entity;
use Modules\Telegram\Models\TelegramAccount;

/**
 * A ready-to-send supplier line. Tests override entity_id/state per case.
 */
class TelegramAccountFactory extends Factory
{
    protected $model = TelegramAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'label' => fake()->company().' Line',
            'phone' => '+855'.fake()->unique()->numerify('#########'),
            'state' => TelegramAccount::STATE_READY,
            'tdlib_base_url' => 'http://tdlib.test',
        ];
    }

    public function waitingCode(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => TelegramAccount::STATE_WAITING_CODE,
        ]);
    }

    public function throttled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'state' => TelegramAccount::STATE_THROTTLED,
            'flood_wait_until' => now()->addMinutes(5),
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
