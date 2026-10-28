<?php

namespace Modules\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\SystemSetting;

/**
 * Auto-generated factory per project rule. Defaults to a non-secret key so
 * factory rows stay readable in tests; secret rows pass an explicit value.
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => 'telegram',
            'key' => 'bot_username',
            'value' => 'factory_bot',
        ];
    }
}
