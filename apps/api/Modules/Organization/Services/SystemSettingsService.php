<?php

namespace Modules\Organization\Services;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Config;
use Modules\Organization\Models\SystemSetting;
use Throwable;

/**
 * Read/write access for the system-settings override store.
 *
 * Security invariants enforced here:
 * - The config overlay maps database rows ONLY through the code-defined
 *   whitelist (config/system-settings.php); unknown group/key pairs are
 *   ignored, so no database content can point the overlay at an arbitrary
 *   config path.
 * - Secret values are write-only: they are decrypted solely to determine
 *   whether an override is active and are never returned to callers.
 * - Values are typed and bounded by the whitelist before persisting, and
 *   stored canonicalized (strings trimmed, booleans '1'/'0', integers
 *   decimal) inside an encrypted cast.
 * - Nothing ever logs values.
 *
 * Note: long-running queue workers boot once and pick up setting changes
 * after their next restart; HTTP requests see changes immediately.
 */
class SystemSettingsService
{
    /**
     * Config path → the value that existed before this service first set it.
     * Lets clearing an override restore the environment default even when
     * the same instance (singleton) serves several updates in one process.
     * Never serialize or expose this; it may hold environment secrets.
     *
     * @var array<string, mixed>
     */
    private array $superseded = [];

    /**
     * Boot-time config overlay: applies every active override to its config
     * path so the rest of the codebase keeps reading plain config() with
     * zero knowledge of this store. Failures are non-fatal — a missing
     * table (fresh environment) or unreadable ciphertext (rotated
     * APP_KEY) leaves the environment value in place.
     */
    public function overlayConfig(): void
    {
        try {
            $rows = SystemSetting::query()->get(['group', 'key', 'value']);
        } catch (Throwable) {
            return;
        }

        $definitions = $this->definitions();
        /** @var array<string, mixed> $active config path => runtime value */
        $active = [];

        foreach ($rows as $row) {
            $definition = $definitions[$row->group]['keys'][$row->key] ?? null;

            if ($definition === null || $row->value === null) {
                continue;
            }

            try {
                $active[$definition['config']] = $this->cast($row->value, $definition);
            } catch (DecryptException) {
                continue;
            }
        }

        foreach ($active as $path => $value) {
            if (! array_key_exists($path, $this->superseded)) {
                $this->superseded[$path] = Config::get($path);
            }

            Config::set($path, $value);
        }

        foreach ($this->superseded as $path => $original) {
            if (! array_key_exists($path, $active)) {
                Config::set($path, $original);
                unset($this->superseded[$path]);
            }
        }
    }

    /**
     * Whitelist for one group, or every group when none is given:
     * definitions straight from config/system-settings.php.
     *
     * @return array<string, mixed>
     */
    public function definitions(?string $group = null): array
    {
        $definitions = config('system-settings', []);

        if (! is_array($definitions)) {
            return [];
        }

        return $group === null ? $definitions : ($definitions[$group] ?? []);
    }

    /**
     * Effective values for every whitelisted key of a group.
     *
     * @return array{group: string, label: string, items: array<int, array<string, mixed>>}
     */
    public function values(string $group): array
    {
        $definition = $this->definitions($group);
        $rows = SystemSetting::query()
            ->where('group', $group)
            ->get()
            ->keyBy('key');

        $items = [];

        foreach ($definition['keys'] ?? [] as $key => $keyDefinition) {
            $stored = $this->storedValue($rows->get($key));
            $configured = $stored !== null;
            $secret = (bool) $keyDefinition['secret'];
            $effective = $configured ? $stored : Config::get($keyDefinition['config']);

            $items[] = [
                'key' => $key,
                'label' => $keyDefinition['label'],
                'type' => $keyDefinition['type'],
                'secret' => $secret,
                'env' => $keyDefinition['env'],
                'configured' => $configured,
                // Secrets are write-only: the effective value is never
                // serialized, no matter where it comes from.
                'value' => $secret ? null : $this->normalize($keyDefinition['type'], $effective),
                'min' => $keyDefinition['min'] ?? null,
                'max' => $keyDefinition['max'] ?? null,
            ];
        }

        return [
            'group' => $group,
            'label' => $definition['label'] ?? $group,
            'items' => $items,
        ];
    }

    /**
     * Persists the submitted subset of keys, applies the overlay so this
     * request (and everything later in it) sees the new configuration, and
     * returns the fresh effective values.
     *
     * Missing keys are left untouched; null or empty-string values clear an
     * override so the environment value applies again.
     *
     * @param  array<string, mixed>  $input
     * @return array{group: string, label: string, items: array<int, array<string, mixed>>}
     */
    public function update(string $group, array $input, User $user): array
    {
        foreach ($input as $key => $value) {
            $definition = $this->definitions($group)['keys'][$key] ?? null;

            if ($definition === null) {
                continue;
            }

            $canonical = $this->canonicalize($value, $definition);
            $row = SystemSetting::query()->firstOrNew(['group' => $group, 'key' => $key]);

            if ($canonical === null && ! $row->exists) {
                continue;
            }

            $row->value = $canonical;
            $row->created_by = $row->created_by ?? $user->getKey();
            $row->updated_by = $user->getKey();
            $row->save();
        }

        $this->overlayConfig();

        return $this->values($group);
    }

    /**
     * Decrypts the override value, returning null when the row is absent,
     * cleared, or unreadable — each of which means "environment value
     * applies".
     */
    private function storedValue(?SystemSetting $row): ?string
    {
        if ($row === null) {
            return null;
        }

        try {
            return $row->value;
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Validates input has already happened (FormRequest, whitelist-driven);
     * this only normalizes to the canonical stored representation.
     */
    private function canonicalize(mixed $value, array $definition): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($definition['type']) {
            'boolean' => ($value === true || $value === 1 || $value === '1') ? '1' : '0',
            'integer' => (string) (int) $value,
            default => trim((string) $value),
        };
    }

    /**
     * Stored canonical string → runtime config value.
     *
     * @param  array<string, mixed>  $definition
     */
    private function cast(string $value, array $definition): mixed
    {
        return match ($definition['type']) {
            'boolean' => $value === '1',
            'integer' => (int) $value,
            default => $value,
        };
    }

    /**
     * Effective value (store or config) → response value of the declared
     * type, so the client always sees a stable shape.
     */
    private function normalize(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (string) $value,
        };
    }
}
