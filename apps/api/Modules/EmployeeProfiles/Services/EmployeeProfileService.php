<?php

namespace Modules\EmployeeProfiles\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\EmployeeProfiles\Models\EmployeeProfile;
use Modules\EmployeeProfiles\Repositories\EmployeeProfileRepositoryInterface;

/**
 * Owns employee-profile history: every change closes the current row and
 * inserts a new current version, so positions and org placement stay auditable.
 */
class EmployeeProfileService
{
    public function __construct(private readonly EmployeeProfileRepositoryInterface $profiles) {}

    /**
     * @param  array<string, mixed>  $data  only the keys the caller supplied
     */
    public function record(User $user, array $data, User $actor): EmployeeProfile
    {
        return DB::transaction(function () use ($user, $data, $actor): EmployeeProfile {
            $current = $this->profiles->currentFor($user);

            $attributes = $this->normalize([
                'card_id' => $this->value($data, 'card_id', $current?->card_id),
                'position' => $this->value($data, 'position', $current?->position),
                'campus_id' => $this->value($data, 'campus_id', $current?->campus_id),
                'division_id' => $this->value($data, 'division_id', $current?->division_id),
                'department_id' => $this->value($data, 'department_id', $current?->department_id),
            ]);

            $effectiveFrom = $this->effectiveFrom($data);

            if ($current !== null && $this->matches($current, $attributes)) {
                return $current;
            }

            if ($current !== null) {
                $this->profiles->update($current, [
                    'is_current' => false,
                    'effective_to' => $effectiveFrom,
                    'updated_by' => $actor->getKey(),
                ]);
            }

            $profile = $this->profiles->create([
                'entity_id' => $user->entity_id,
                'user_id' => $user->getKey(),
                ...$attributes,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'is_current' => true,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            return $profile->load(['campus', 'division', 'department']);
        });
    }

    /**
     * The company directory is the source of truth for positions, but a login
     * payload without one must never wipe what we already store.
     */
    public function applyCompanyPosition(User $user, ?string $position): void
    {
        if ($position === null || trim($position) === '') {
            return;
        }

        $this->record($user, ['position' => $position], $user);
    }

    private function value(array $data, string $key, mixed $fallback): mixed
    {
        return array_key_exists($key, $data) ? $data[$key] : $fallback;
    }

    private function effectiveFrom(array $data): string
    {
        $value = $data['effective_from'] ?? null;

        return is_string($value) && $value !== ''
            ? $value
            : now()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalize(array $attributes): array
    {
        return array_map(
            fn (mixed $value): mixed => $value === '' ? null : $value,
            $attributes,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function matches(EmployeeProfile $profile, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if ((string) $profile->getAttribute($key) !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
