<?php

namespace Modules\Telegram\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Organization\Models\Entity;
use Modules\Telegram\Models\TelegramAccount;
use Modules\Telegram\Models\TelegramContact;
use Modules\Telegram\Models\TelegramMessage;

class TelegramMessageFactory extends Factory
{
    protected $model = TelegramMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => $this->resolveEntity(),
            'direction' => TelegramMessage::DIRECTION_OUT,
            'telegram_account_id' => TelegramAccount::factory(),
            'telegram_contact_id' => TelegramContact::factory(),
            'body' => fake()->sentence(),
            'status' => TelegramMessage::STATUS_QUEUED,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function inbound(int $chatId, int $telegramMessageId): static
    {
        return $this->state(fn (array $attributes): array => [
            'direction' => TelegramMessage::DIRECTION_IN,
            'telegram_chat_id' => $chatId,
            'telegram_message_id' => $telegramMessageId,
            'status' => TelegramMessage::STATUS_RECEIVED,
            'idempotency_key' => "in:{$chatId}:{$telegramMessageId}",
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
