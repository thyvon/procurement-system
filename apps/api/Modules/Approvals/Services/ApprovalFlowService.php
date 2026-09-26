<?php

namespace Modules\Approvals\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;

/**
 * Writes for the approval configuration (flows, amount bands, ordered
 * steps). Amount-band integrity is validated here against the whole
 * setting: `ApprovalService::resolveFlow` picks the first active flow
 * whose band covers the amount, so active bands must anchor at 0.00 and
 * never overlap (overlaps would make the pick ambiguous). Gaps between
 * bands are tolerated — the seeded convention uses whole-dollar
 * boundaries (0–1,000 / 1,001+), and resolveFlow's "No approval flow
 * covers the amount" message remains the runtime guard.
 *
 * Steps are replaced wholesale on every edit (positions come from the
 * payload order); `approval_requests.snapshot` freezes the definition at
 * submit time, so editing a flow never corrupts in-flight requests.
 */
class ApprovalFlowService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): ApprovalFlow
    {
        $setting = $this->settingOrFail($data['subject_type']);

        $min = (float) $data['min_amount'];
        $max = $this->amountOrNull($data['max_amount'] ?? null);
        $active = (bool) ($data['is_active'] ?? true);
        $steps = $this->normalizeSteps($data['steps']);

        $this->assertBandOrder($min, $max);
        $this->assertBandsValid($setting, null, $min, $max, $active);

        return DB::transaction(function () use ($setting, $data, $min, $max, $active, $steps, $user): ApprovalFlow {
            $flow = ApprovalFlow::query()->create([
                'approval_setting_id' => $setting->getKey(),
                'code' => $this->nextCode($data['name']),
                'name' => $data['name'],
                'min_amount' => $min,
                'max_amount' => $max,
                'is_active' => $active,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            $this->replaceSteps($flow, $steps, $user);

            return $flow->load(['steps', 'setting']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ApprovalFlow $flow, array $data, User $user): ApprovalFlow
    {
        $setting = $flow->setting;

        if ($setting === null) {
            throw ValidationException::withMessages([
                'subject_type' => 'No approval workflow is configured for this document type.',
            ]);
        }

        $min = array_key_exists('min_amount', $data) ? (float) $data['min_amount'] : (float) $flow->min_amount;
        $max = array_key_exists('max_amount', $data)
            ? $this->amountOrNull($data['max_amount'])
            : $this->amountOrNull($flow->max_amount);
        $active = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) $flow->is_active;
        $steps = array_key_exists('steps', $data) ? $this->normalizeSteps($data['steps']) : null;

        $this->assertBandOrder($min, $max);
        $this->assertBandsValid($setting, $flow, $min, $max, $active);

        return DB::transaction(function () use ($flow, $data, $min, $max, $active, $steps, $user): ApprovalFlow {
            $flow->fill([
                'name' => $data['name'] ?? $flow->name,
                'min_amount' => $min,
                'max_amount' => $max,
                'is_active' => $active,
                'updated_by' => $user->getKey(),
            ])->save();

            if ($steps !== null) {
                $this->replaceSteps($flow, $steps, $user);
            }

            return $flow->load(['steps', 'setting']);
        });
    }

    public function destroy(ApprovalFlow $flow): void
    {
        $pending = ApprovalRequest::query()
            ->where('approval_flow_id', $flow->getKey())
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'code' => 'This flow cannot be deleted while a pending approval request still uses it.',
            ]);
        }

        $flow->delete();
    }

    private function settingOrFail(string $subjectType): ApprovalSetting
    {
        $setting = ApprovalSetting::query()
            ->where('subject_type', $subjectType)
            ->where('is_active', true)
            ->first();

        if ($setting === null) {
            throw ValidationException::withMessages([
                'subject_type' => 'No approval workflow is configured for this document type.',
            ]);
        }

        return $setting;
    }

    private function amountOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * The flow `code` is a server-side slug of the name (unique per entity),
     * mirroring how other codes are generated server-side only.
     */
    private function nextCode(string $name): string
    {
        $base = Str::limit(Str::slug($name), 58, '');
        $base = $base === '' ? 'flow' : $base;

        $code = $base;
        $suffix = 2;

        while (ApprovalFlow::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    private function assertBandOrder(float $min, ?float $max): void
    {
        if ($max !== null && $max < $min) {
            throw ValidationException::withMessages([
                'max_amount' => 'The maximum amount must be greater than or equal to the minimum amount.',
            ]);
        }
    }

    /**
     * The active bands of a setting must anchor at 0.00 and never overlap —
     * evaluated over every active flow plus the proposed values, so an edit
     * can never make the flow pick ambiguous.
     */
    private function assertBandsValid(ApprovalSetting $setting, ?ApprovalFlow $current, float $min, ?float $max, bool $active): void
    {
        /** @var array<int, array{0: float, 1: float|null}> $rows */
        $rows = [];

        foreach ($setting->flows()->get() as $existing) {
            if ($current !== null && $existing->is($current)) {
                continue;
            }

            if (! $existing->is_active) {
                continue;
            }

            $rows[] = [(float) $existing->min_amount, $this->amountOrNull($existing->max_amount)];
        }

        if ($active) {
            $rows[] = [$min, $max];
        }

        if ($rows === []) {
            return;
        }

        usort($rows, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        if (round($rows[0][0], 2) > 0) {
            throw ValidationException::withMessages([
                'min_amount' => 'Active amount bands must start at 0.00 so every amount is covered.',
            ]);
        }

        for ($i = 1, $count = count($rows); $i < $count; $i++) {
            $previous = $rows[$i - 1];

            if ($previous[1] === null) {
                throw ValidationException::withMessages([
                    'min_amount' => 'An open-ended band (no maximum) cannot be followed by another active band.',
                ]);
            }

            if (round($rows[$i][0], 2) <= round($previous[1], 2)) {
                throw ValidationException::withMessages([
                    'min_amount' => 'Active amount bands must not overlap — the next band must start above '
                        .number_format($previous[1], 2, '.', '').'.',
                ]);
            }
        }
    }

    /**
     * Payload order is authoritative for positions. Record steps always store
     * no actions; decision steps need at least one, and a flow needs at least
     * one decision step.
     *
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array{position: int, key: string, label: string, action_mode: string, allowed_actions: array<int, string>|null, show_on_print: bool}>
     */
    private function normalizeSteps(array $steps): array
    {
        $steps = array_values($steps);
        $keys = array_column($steps, 'key');

        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages([
                'steps' => 'Step keys must be unique within a flow.',
            ]);
        }

        $normalized = [];
        $decisions = 0;

        foreach ($steps as $index => $step) {
            $mode = (string) $step['action_mode'];
            $actions = null;

            if ($mode === ApprovalStep::MODE_DECIDE) {
                $actions = array_values($step['allowed_actions'] ?? []);

                if ($actions === []) {
                    throw ValidationException::withMessages([
                        'steps' => 'Every decision step needs at least one allowed action.',
                    ]);
                }

                $decisions++;
            }

            $normalized[] = [
                'position' => $index + 1,
                'key' => (string) $step['key'],
                'label' => (string) $step['label'],
                'action_mode' => $mode,
                'allowed_actions' => $actions,
                'show_on_print' => (bool) ($step['show_on_print'] ?? true),
            ];
        }

        if ($decisions === 0) {
            throw ValidationException::withMessages([
                'steps' => 'A flow needs at least one decision step.',
            ]);
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{position: int, key: string, label: string, action_mode: string, allowed_actions: array<int, string>|null, show_on_print: bool}>  $steps
     */
    private function replaceSteps(ApprovalFlow $flow, array $steps, User $user): void
    {
        $flow->steps()->forceDelete();

        foreach ($steps as $attributes) {
            ApprovalStep::query()->create([
                ...$attributes,
                'entity_id' => $flow->entity_id,
                'approval_flow_id' => $flow->getKey(),
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);
        }
    }
}
