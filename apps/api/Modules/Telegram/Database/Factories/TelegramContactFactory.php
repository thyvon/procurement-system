<?php

namespace Modules\Telegram\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Entity;
use Modules\Telegram\Models\TelegramAccount;
use Modules\Telegram\Models\TelegramContact;

/**
 * A supplier contact. Telegram ids stay null until the pod resolves the
 * phone — tests set telegram_chat_id explicitly when exercising inbound.
 */
class TelegramContactFactory extends Factory
{
    protected $model = TelegramContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'name' => fake()->name(),
            'phone' => '+855'.fake()->unique()->numerify('#########'),
            'telegram_account_id' => TelegramAccount::factory(),
            'language' => TelegramContact::LANGUAGE_EN,
        ];
    }

    public function withChat(int $chatId): static
    {
        return $this->state(fn (array $attributes): array => [
            'telegram_chat_id' => $chatId,
            'telegram_user_id' => $chatId,
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
