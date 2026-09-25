<?php

namespace Modules\Approvals\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approvals\Events\ApprovalStatusChanged;
use Modules\Approvals\Models\ApprovalAction;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Approvals\Notifications\ActionRequiredNotification;
use Modules\Approvals\Notifications\ApprovalResultNotification;

class ApprovalService
{
    public function __construct(private readonly ApprovalSubjectRegistry $subjects) {}

    /**
     * Resolves the flow a document would use and returns everything the
     * submit dialog needs: ordered steps + TOCA-filtered candidates.
     *
     * @return array<string, mixed>
     */
    public function preview(string $subjectType, string $subjectId): array
    {
        $subject = $this->subjects->find($subjectType, $subjectId);
        $amount = $this->subjects->amount($subjectType, $subject);
        [$setting, $flow] = $this->resolveFlow($subjectType, $amount);

        $steps = $flow->steps()->get();
        $candidates = $this->subjects->candidates($subjectType, $amount)
            ->map(fn (User $user): array => ['id' => $user->getKey(), 'name' => $user->name])
            ->values();

        return [
            'subjectType' => $subjectType,
            'subjectId' => (string) $subject->getKey(),
            'documentCode' => $this->subjects->code($subjectType, $subject),
            'amount' => number_format($amount, 2, '.', ''),
            'flow' => $this->flowPayload($flow),
            'steps' => $steps->map(function (ApprovalStep $step) use ($candidates): array {
                return [
                    'position' => $step->position,
                    'key' => $step->key,
                    'label' => $step->label,
                    'actionMode' => $step->action_mode,
                    'allowedActions' => $step->isDecide() ? ($step->allowed_actions ?? []) : [],
                    'candidates' => $step->isDecide() ? $candidates->all() : null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array<int|string, int|string>  $assignees  step position => user id
     */
    public function submit(string $subjectType, string $subjectId, array $assignees, User $user): ApprovalRequest
    {
        return DB::transaction(function () use ($subjectType, $subjectId, $assignees, $user): ApprovalRequest {
            $subject = $this->subjects->find($subjectType, $subjectId);
            $amount = $this->subjects->amount($subjectType, $subject);
            [$setting, $flow] = $this->resolveFlow($subjectType, $amount);

            $pendingExists = ApprovalRequest::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', (string) $subject->getKey())
                ->where('status', ApprovalRequest::STATUS_PENDING)
                ->exists();

            if ($pendingExists) {
                throw ValidationException::withMessages([
                    'subjectId' => 'A pending approval request already exists for this document.',
                ]);
            }

            $steps = $flow->steps()->get();
            $snapshotSteps = [];
            $errors = [];

            foreach ($steps as $step) {
                $assignedTo = $step->isDecide()
                    ? (int) ($assignees[$step->position] ?? $assignees[(string) $step->position] ?? 0)
                    : 0;

                if ($step->isDecide()) {
                    if ($assignedTo <= 0) {
                        $errors["assignees.{$step->position}"] = "A responsible user is required for the {$step->label} step.";
                    } elseif (! $this->subjects->canAct($subjectType, $assignedTo, $amount)) {
                        $errors["assignees.{$step->position}"] = "The selected user's commitment authority does not cover this amount.";
                    }
                }

                $snapshotSteps[] = [
                    'position' => $step->position,
                    'key' => $step->key,
                    'label' => $step->label,
                    'actionMode' => $step->action_mode,
                    'allowedActions' => $step->isDecide() ? ($step->allowed_actions ?? []) : [],
                    'assigneeId' => $assignedTo > 0 ? $assignedTo : null,
                    'assigneeName' => null,
                ];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $assigneeIds = array_values(array_filter(array_column($snapshotSteps, 'assigneeId')));

            if ($assigneeIds !== []) {
                $names = User::query()->whereIn('id', $assigneeIds)->pluck('name', 'id');

                foreach ($snapshotSteps as $index => $step) {
                    if ($step['assigneeId'] !== null) {
                        $snapshotSteps[$index]['assigneeName'] = $names[$step['assigneeId']] ?? null;
                    }
                }
            }

            $request = ApprovalRequest::create([
                'entity_id' => $user->entity_id,
                'subject_type' => $subjectType,
                'subject_id' => (string) $subject->getKey(),
                'approval_setting_id' => $setting->getKey(),
                'approval_flow_id' => $flow->getKey(),
                'amount_snapshot' => $amount,
                'status' => ApprovalRequest::STATUS_PENDING,
                'snapshot' => [
                    'documentCode' => $this->subjects->code($subjectType, $subject),
                    'flow' => $this->flowPayload($flow),
                    'steps' => $snapshotSteps,
                ],
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            $this->parkOnNextStep($request, $snapshotSteps, -1, $user);
            $request->refresh();

            if ($request->status === ApprovalRequest::STATUS_PENDING) {
                $this->dispatchStatusChanged($request, (int) $user->getKey());
            }

            return $request;
        });
    }

    public function act(ApprovalRequest $approval, string $action, ?string $comment, User $user): ApprovalRequest
    {
        return DB::transaction(function () use ($approval, $action, $comment, $user): ApprovalRequest {
            $request = ApprovalRequest::query()
                ->whereKey($approval->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'action' => 'This approval request is no longer pending.',
                ]);
            }

            if ((int) $request->current_assignee_id !== (int) $user->getKey()) {
                abort(403, 'You are not the responsible user for the current step.');
            }

            if (! $this->subjects->canAct($request->subject_type, $user->getKey(), (float) $request->amount_snapshot)) {
                abort(403, 'Your commitment authority no longer covers this amount.');
            }

            $current = collect($request->snapshot['steps'])
                ->firstWhere('position', $request->current_position);

            $allowed = $current['allowedActions'] ?? [];

            if (! in_array($action, $allowed, true)) {
                throw ValidationException::withMessages([
                    'action' => "The {$action} action is not allowed at the {$current['label']} step.",
                ]);
            }

            $this->recordAction($request, $current, $action, $user, $comment);

            if ($action === 'approve') {
                $this->parkOnNextStep(
                    $request,
                    $request->snapshot['steps'],
                    (int) $request->current_position,
                    $user,
                );

                return $request->refresh();
            }

            $status = $action === 'reject' ? ApprovalRequest::STATUS_REJECTED : ApprovalRequest::STATUS_RETURNED;

            $request->update([
                'status' => $status,
                'current_position' => null,
                'current_assignee_id' => null,
                'decided_at' => now(),
                'updated_by' => $user->getKey(),
            ]);

            $this->notifySubmitter($request, $status);
            $this->dispatchStatusChanged($request, (int) $user->getKey());

            return $request->refresh();
        });
    }

    /**
     * Stamps any record steps at or before $afterPosition, then parks the
     * request on the next decide step (notifying its assignee) — or completes
     * the request when no decide step remains.
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function parkOnNextStep(ApprovalRequest $request, array $steps, int $afterPosition, User $actor): void
    {
        foreach ($steps as $index => $step) {
            if ($step['position'] <= $afterPosition) {
                continue;
            }

            if ($step['actionMode'] === ApprovalStep::MODE_RECORD) {
                $this->recordAction($request, $step, 'record', $request->creator ?? $actor);

                continue;
            }

            $request->update([
                'current_position' => $step['position'],
                'current_assignee_id' => $step['assigneeId'],
                'updated_by' => $actor->getKey(),
            ]);

            $assignee = User::query()->find($step['assigneeId']);

            $assignee?->notify(new ActionRequiredNotification([
                'requestId' => $request->getKey(),
                'subjectType' => $request->subject_type,
                'subjectId' => $request->subject_id,
                'documentCode' => $request->snapshot['documentCode'] ?? null,
                'stepLabel' => $step['label'],
                'amount' => number_format((float) $request->amount_snapshot, 2, '.', ''),
            ]));

            return;
        }

        $request->update([
            'status' => ApprovalRequest::STATUS_APPROVED,
            'current_position' => null,
            'current_assignee_id' => null,
            'decided_at' => now(),
            'updated_by' => $actor->getKey(),
        ]);

        $this->notifySubmitter($request, ApprovalRequest::STATUS_APPROVED);
        $this->dispatchStatusChanged($request, (int) $actor->getKey());
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function recordAction(ApprovalRequest $request, array $step, string $action, User $actor, ?string $comment = null): ApprovalAction
    {
        return ApprovalAction::create([
            'entity_id' => $request->entity_id,
            'approval_request_id' => $request->getKey(),
            'step_position' => $step['position'],
            'step_key' => $step['key'],
            'action' => $action,
            'comment' => $comment,
            'acted_by' => $actor->getKey(),
            'acted_at' => now(),
        ]);
    }

    private function notifySubmitter(ApprovalRequest $request, string $status): void
    {
        $submitter = User::query()->find($request->created_by);

        $submitter?->notify(new ApprovalResultNotification([
            'requestId' => $request->getKey(),
            'subjectType' => $request->subject_type,
            'subjectId' => $request->subject_id,
            'documentCode' => $request->snapshot['documentCode'] ?? null,
            'status' => $status,
            'amount' => number_format((float) $request->amount_snapshot, 2, '.', ''),
        ]));
    }

    private function dispatchStatusChanged(ApprovalRequest $request, ?int $actedBy = null): void
    {
        event(new ApprovalStatusChanged(
            $request->subject_type,
            $request->subject_id,
            $request->status,
            $request->getKey(),
            $actedBy,
        ));
    }

    /**
     * @return array{0: ApprovalSetting, 1: ApprovalFlow}
     */
    private function resolveFlow(string $subjectType, float $amount): array
    {
        $setting = ApprovalSetting::query()
            ->where('subject_type', $subjectType)
            ->where('is_active', true)
            ->first();

        if ($setting === null) {
            throw ValidationException::withMessages([
                'subjectType' => 'No approval workflow is configured for this document type.',
            ]);
        }

        $flow = $setting->flows()
            ->where('is_active', true)
            ->get()
            ->first(fn (ApprovalFlow $flow): bool => $flow->coversAmount($amount));

        if ($flow === null) {
            throw ValidationException::withMessages([
                'subjectId' => 'No approval flow covers the amount '.number_format($amount, 2, '.', '').'.',
            ]);
        }

        return [$setting, $flow];
    }

    /**
     * @return array<string, mixed>
     */
    private function flowPayload(ApprovalFlow $flow): array
    {
        return [
            'id' => $flow->getKey(),
            'code' => $flow->code,
            'name' => $flow->name,
            'minAmount' => $flow->min_amount,
            'maxAmount' => $flow->max_amount,
        ];
    }
}
