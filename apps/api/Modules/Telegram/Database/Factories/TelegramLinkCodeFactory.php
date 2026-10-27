<?php

namespace Modules\Telegram\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Entity;
use Modules\Telegram\Models\TelegramLinkCode;

/**
 * A /start deep-link code. Defaults to a fresh redeemable code; tests flip
 * it with expired() / used() when exercising the redemption rules.
 */
class TelegramLinkCodeFactory extends Factory
{
    protected $model = TelegramLinkCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'user_id' => User::factory(),
            'code' => fake()->unique()->regexify('[A-Za-z0-9_-]{32}'),
            'expires_at' => now()->addMinutes(10),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes): array => [
            'used_at' => now(),
            'telegram_chat_id' => 999001,
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
