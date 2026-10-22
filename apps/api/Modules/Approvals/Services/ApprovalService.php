<?php

namespace Modules\Approvals\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approvals\Events\ApprovalStatusChanged;
use Modules\Approvals\Models\ApprovalAction;
use Modules\Approvals\Models\ApprovalDraft;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalRequestStepAssignee;
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
     * `$subjectId` may be null for a document that has not been created yet —
     * the flow is then resolved purely from the draft `$amountOverride`, which
     * is why an amount is required in that case.
     * `$amountOverride` only affects this preview — submit always re-reads the
     * amount from the subject record.
     *
     * @return array<string, mixed>
     */
    public function preview(string $subjectType, ?string $subjectId, ?float $amountOverride = null): array
    {
        $subject = $subjectId !== null ? $this->subjects->find($subjectType, $subjectId) : null;

        if ($subject !== null) {
            $amount = $amountOverride ?? $this->subjects->amount($subjectType, $subject);
        } elseif ($amountOverride !== null) {
            $amount = $amountOverride;
        } else {
            throw ValidationException::withMessages([
                'amount' => 'An amount is required to preview a document that has not been saved yet.',
            ]);
        }

        [$setting, $flow] = $this->resolveFlow($subjectType, $amount);

        $steps = $flow->steps()->get();

        return [
            'subjectType' => $subjectType,
            'subjectId' => $subject !== null ? (string) $subject->getKey() : null,
            'documentCode' => $subject !== null ? $this->subjects->code($subjectType, $subject) : null,
            'amount' => number_format($amount, 2, '.', ''),
            'flow' => $this->flowPayload($flow),
            'assignees' => $subject !== null ? $this->draftAssignees($subjectType, $subject) : null,
            'steps' => $steps->map(function (ApprovalStep $step) use ($subjectType, $amount): array {
                return [
                    'position' => $step->position,
                    'key' => $step->key,
                    'label' => $step->label,
                    'labelKh' => $step->label_kh,
                    'actionMode' => $step->action_mode,
                    'allowedActions' => $step->isDecide() ? ($step->allowed_actions ?? []) : [],
                    'showOnPrint' => $step->show_on_print,
                    'candidates' => $step->isDecide()
                        ? $this->subjects->candidates($subjectType, $amount, $step->key)
                            ->map(fn (User $user): array => [
                                'id' => $user->getKey(),
                                'name' => $user->name,
                                'position' => $user->position,
                            ])
                            ->values()
                            ->all()
                        : null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Persists the approver selection a user made in the submit panel without
     * creating the approval request — "Save" stores it, "Submit" consumes it.
     * Each position may hold several users (co-signed steps).
     *
     * @param  array<int|string, mixed>  $assignees  step position => user id or list of user ids
     * @return array<string, mixed>
     */
    public function saveDraft(string $subjectType, string $subjectId, array $assignees, User $user): array
    {
        $subject = $this->subjects->find($subjectType, $subjectId);

        $normalized = $this->normalizeAssignees($assignees);

        $draft = ApprovalDraft::query()->firstOrNew([
            'entity_id' => $user->entity_id,
            'subject_type' => $subjectType,
            'subject_id' => (string) $subject->getKey(),
        ]);
        $draft->assignees = $normalized;
        $draft->updated_by = $user->getKey();
        if (! $draft->exists) {
            $draft->created_by = $user->getKey();
        }
        $draft->save();

        return [
            'subjectType' => $subjectType,
            'subjectId' => (string) $subject->getKey(),
            'assignees' => $normalized,
        ];
    }

    public function submit(string $subjectType, string $subjectId, array $assignees, User $user): ApprovalRequest
    {
        return DB::transaction(function () use ($subjectType, $subjectId, $assignees, $user): ApprovalRequest {
            $subject = $this->subjects->find($subjectType, $subjectId);
            $amount = $this->subjects->amount($subjectType, $subject);

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

            $this->subjects->ensureSubmittable($subjectType, $subject);

            [$setting, $flow] = $this->resolveFlow($subjectType, $amount);

            $steps = $flow->steps()->get();
            $assigneesByPosition = $this->normalizeAssignees($assignees);
            $snapshotSteps = [];
            $errors = [];
            $stepAssigneeIds = [];

            foreach ($steps as $step) {
                $ids = $step->isDecide()
                    ? ($assigneesByPosition[$step->position] ?? $assigneesByPosition[(string) $step->position] ?? [])
                    : [];
                $stepAssigneeIds[$step->position] = $ids;

                if ($step->isDecide()) {
                    if ($ids === []) {
                        $errors["assignees.{$step->position}"] = "A responsible user is required for the {$step->label} step.";
                    } else {
                        foreach ($ids as $userId) {
                            if (! $this->subjects->canAct($subjectType, $userId, $amount, $step->key)) {
                                $errors["assignees.{$step->position}"] = "The selected user's commitment authority does not allow the {$step->label} step.";
                                break;
                            }
                        }
                    }
                }

                $snapshotSteps[] = [
                    'position' => $step->position,
                    'key' => $step->key,
                    'label' => $step->label,
                    'labelKh' => $step->label_kh,
                    'actionMode' => $step->action_mode,
                    'allowedActions' => $step->isDecide() ? ($step->allowed_actions ?? []) : [],
                    'showOnPrint' => $step->show_on_print,
                    'assigneeId' => $ids[0] ?? null,
                    'assignees' => [],
                    'assigneeName' => null,
                    'assigneePosition' => null,
                    'assigneeSignature' => null,
                ];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $assigneeIds = collect($stepAssigneeIds)->flatten()->unique()->values()->all();

            $assignees = collect();

            if ($assigneeIds !== []) {
                $assignees = User::query()
                    ->whereIn('id', $assigneeIds)
                    ->get(['id', 'name', 'position', 'signature_path'])
                    ->keyBy('id');
            }

            // The acting user instance may carry a partial attribute set (strict
            // mode); read the submitter's frozen identity from a fresh row.
            $submitter = User::query()
                ->select(['id', 'name', 'position', 'signature_path'])
                ->findOrFail($user->getKey());

            foreach ($snapshotSteps as $index => $step) {
                $ids = $stepAssigneeIds[$step['position']] ?? [];

                if ($ids !== []) {
                    $signers = collect($ids)
                        ->map(fn (int $assigneeId): ?User => $assignees->get($assigneeId))
                        ->filter()
                        ->map(fn (User $assignee): array => [
                            'id' => $assignee->getKey(),
                            'name' => $assignee->name,
                            'position' => $assignee->position,
                            'signature' => $assignee->signature_path,
                        ])
                        ->values()
                        ->all();

                    $snapshotSteps[$index]['assignees'] = $signers;

                    $firstSigner = $signers[0] ?? null;
                    $snapshotSteps[$index]['assigneeName'] = $firstSigner['name'] ?? null;
                    $snapshotSteps[$index]['assigneePosition'] = $firstSigner['position'] ?? null;
                    $snapshotSteps[$index]['assigneeSignature'] = $firstSigner['signature'] ?? null;
                } elseif ($step['actionMode'] === ApprovalStep::MODE_RECORD) {
                    $snapshotSteps[$index]['assigneeName'] = $submitter->name;
                    $snapshotSteps[$index]['assigneePosition'] = $submitter->position;
                    $snapshotSteps[$index]['assigneeSignature'] = $submitter->signature_path;
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
                    'document' => $this->subjects->documentSnapshot($subjectType, $subject),
                    'flow' => $this->flowPayload($flow),
                    'steps' => $snapshotSteps,
                ],
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            // The saved draft has served its purpose once the request exists.
            ApprovalDraft::query()
                ->where('subject_type', $subjectType)
                ->where('subject_id', (string) $subject->getKey())
                ->delete();

            foreach ($stepAssigneeIds as $position => $ids) {
                foreach ($ids as $assigneeId) {
                    ApprovalRequestStepAssignee::query()->create([
                        'entity_id' => $user->entity_id,
                        'approval_request_id' => $request->getKey(),
                        'step_position' => (int) $position,
                        'user_id' => $assigneeId,
                    ]);
                }
            }

            $this->parkOnNextStep($request, $snapshotSteps, -1, $user);
            $request->refresh();

            if ($request->status === ApprovalRequest::STATUS_PENDING) {
                $this->dispatchStatusChanged($request, (int) $user->getKey());
            }

            return $request;
        });
    }

    /**
     * The submitter pulls their own request back before anyone decides it.
     * The subject returns to draft so it can be corrected and submitted again;
     * the cancelled row stays as the audit trail of the withdrawn round.
     */
    public function cancel(ApprovalRequest $approval, User $user): ApprovalRequest
    {
        return DB::transaction(function () use ($approval, $user): ApprovalRequest {
            $request = ApprovalRequest::query()
                ->whereKey($approval->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $request->isPending()) {
                throw ValidationException::withMessages([
                    'status' => "Only a pending approval request can be cancelled, this one is '{$request->status}'.",
                ]);
            }

            if ((int) $request->created_by !== (int) $user->getKey()) {
                abort(403, 'Only the submitter can cancel this approval request.');
            }

            $current = collect($request->snapshot['steps'])
                ->firstWhere('position', $request->current_position);

            $this->recordAction($request, [
                'position' => (int) ($current['position'] ?? $request->current_position ?? 0),
                'key' => $current['key'] ?? 'cancel',
                'label' => $current['label'] ?? 'Cancelled',
            ], 'cancel', $user);

            // Read the assignee before the update clears the step.
            $this->notifyCancelled($request);

            // The withdraw releases every co-signer who never acted.
            ApprovalRequestStepAssignee::query()
                ->where('approval_request_id', $request->getKey())
                ->whereNull('acted_at')
                ->update(['acted_at' => now()]);

            $request->update([
                'status' => ApprovalRequest::STATUS_CANCELLED,
                'current_position' => null,
                'current_assignee_id' => null,
                'decided_at' => null,
                'updated_by' => $user->getKey(),
            ]);

            $this->dispatchStatusChanged($request, (int) $user->getKey());

            return $request->refresh();
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

            $assigneeRow = ApprovalRequestStepAssignee::query()
                ->where('approval_request_id', $request->getKey())
                ->where('step_position', $request->current_position)
                ->where('user_id', $user->getKey())
                ->whereNull('acted_at')
                ->first();

            // Co-signed steps authorize any responsible user who still owes the
            // decision; rounds opened before co-signing existed fall back to the
            // single frozen assignee.
            if ($assigneeRow === null && (int) $request->current_assignee_id !== (int) $user->getKey()) {
                abort(403, 'You are not the responsible user for the current step.');
            }

            $current = collect($request->snapshot['steps'])
                ->firstWhere('position', $request->current_position);

            if (! $this->subjects->canAct($request->subject_type, $user->getKey(), (float) $request->amount_snapshot, $current['key'] ?? null)) {
                abort(403, 'Your commitment authority no longer allows this step.');
            }

            $allowed = $current['allowedActions'] ?? [];

            if (! in_array($action, $allowed, true)) {
                throw ValidationException::withMessages([
                    'action' => "The {$action} action is not allowed at the {$current['label']} step.",
                ]);
            }

            $this->recordAction($request, $current, $action, $user, $comment);

            if ($action === 'approve') {
                $assigneeRow?->update(['acted_at' => now()]);

                $remaining = $this->pendingAssigneeIds($request, $current);

                if ($remaining !== []) {
                    // Every co-signer must approve — the round stays on this
                    // step, parked on the next one who still owes a decision.
                    $request->update([
                        'current_assignee_id' => $remaining[0],
                        'updated_by' => $user->getKey(),
                    ]);

                    return $request->refresh();
                }

                $this->parkOnNextStep(
                    $request,
                    $request->snapshot['steps'],
                    (int) $request->current_position,
                    $user,
                );

                return $request->refresh();
            }

            // A single reject/return closes the round for everyone — release
            // every co-signer who never acted, on this and any later step.
            ApprovalRequestStepAssignee::query()
                ->where('approval_request_id', $request->getKey())
                ->whereNull('acted_at')
                ->update(['acted_at' => now()]);

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
     * The approver selection saved by "Save" for this document, if any —
     * string-keyed step position => list of user ids.
     *
     * @return array<string, array<int, int>>|null
     */
    private function draftAssignees(string $subjectType, Model $subject): ?array
    {
        $draft = ApprovalDraft::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', (string) $subject->getKey())
            ->first();

        if ($draft === null) {
            return null;
        }

        $assignees = [];
        foreach ((array) $draft->assignees as $position => $value) {
            $ids = array_values(array_unique(array_filter(array_map('intval', is_array($value) ? $value : [$value]))));

            if ($ids !== []) {
                $assignees[(string) $position] = $ids;
            }
        }

        return $assignees;
    }

    /**
     * Stamps any record steps at or before $afterPosition, then parks the
     * request on the next decide step (notifying every co-signer who still
     * owes the decision) — or completes the request when no decide step remains.
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function parkOnNextStep(ApprovalRequest $request, array $steps, int $afterPosition, User $actor): void
    {
        foreach ($steps as $step) {
            if ($step['position'] <= $afterPosition) {
                continue;
            }

            if ($step['actionMode'] === ApprovalStep::MODE_RECORD) {
                $this->recordAction($request, $step, 'record', $request->creator ?? $actor);

                continue;
            }

            $assigneeIds = $this->pendingAssigneeIds($request, $step);

            if ($assigneeIds === []) {
                $hasRows = ApprovalRequestStepAssignee::query()
                    ->where('approval_request_id', $request->getKey())
                    ->where('step_position', $step['position'])
                    ->exists();

                if ($hasRows) {
                    // Every co-signer of this step already acted — move on.
                    continue;
                }

                // Rounds opened before co-signing existed carry no rows —
                // park on the frozen snapshot assignees.
                $assigneeIds = $this->snapshotAssigneeIds($step);
            }

            if ($assigneeIds === []) {
                continue;
            }

            $request->update([
                'current_position' => $step['position'],
                'current_assignee_id' => $assigneeIds[0],
                'updated_by' => $actor->getKey(),
            ]);

            $this->notifyAssignees($request, $step, $assigneeIds);

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
     * Users at the snapshot step who still owe a decision. Co-signed rounds
     * track this in the pivot; rounds predating it have no rows, so callers
     * fall back to the frozen snapshot.
     *
     * @param  array<string, mixed>  $step
     * @return array<int, int>
     */
    private function pendingAssigneeIds(ApprovalRequest $request, array $step): array
    {
        return ApprovalRequestStepAssignee::query()
            ->where('approval_request_id', $request->getKey())
            ->where('step_position', (int) $step['position'])
            ->whereNotNull('user_id')
            ->whereNull('acted_at')
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The frozen assignee ids of a round opened before co-signing existed.
     *
     * @param  array<string, mixed>  $step
     * @return array<int, int>
     */
    private function snapshotAssigneeIds(array $step): array
    {
        $ids = collect($step['assignees'] ?? [])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($ids === []) {
            $legacy = (int) ($step['assigneeId'] ?? 0);

            return $legacy > 0 ? [$legacy] : [];
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<int, int>  $assigneeIds
     */
    private function notifyAssignees(ApprovalRequest $request, array $step, array $assigneeIds): void
    {
        $users = User::query()->whereIn('id', $assigneeIds)->get();

        foreach ($users as $assignee) {
            $assignee->notify(new ActionRequiredNotification([
                'requestId' => $request->getKey(),
                'subjectType' => $request->subject_type,
                'subjectId' => $request->subject_id,
                'documentCode' => $request->snapshot['documentCode'] ?? null,
                'stepLabel' => $step['label'],
                'amount' => number_format((float) $request->amount_snapshot, 2, '.', ''),
            ]));
        }
    }

    /**
     * One position => list of user ids; scalars and empty/negative ids are
     * normalized away (a position with no valid pick simply drops out).
     *
     * @param  array<int|string, mixed>  $assignees
     * @return array<string, array<int, int>>
     */
    private function normalizeAssignees(array $assignees): array
    {
        $normalized = [];

        foreach ($assignees as $position => $value) {
            $ids = array_map('intval', is_array($value) ? array_values($value) : [$value]);
            $ids = array_values(array_unique(array_filter($ids, fn (int $id): bool => $id > 0)));

            if ($ids !== []) {
                $normalized[(string) $position] = $ids;
            }
        }

        return $normalized;
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

    /**
     * The assignee loses a task they never acted on — without this the step
     * would silently vanish from their tray.
     */
    private function notifyCancelled(ApprovalRequest $request): void
    {
        $assignee = User::query()->find($request->current_assignee_id);

        $assignee?->notify(new ApprovalResultNotification([
            'requestId' => $request->getKey(),
            'subjectType' => $request->subject_type,
            'subjectId' => $request->subject_id,
            'documentCode' => $request->snapshot['documentCode'] ?? null,
            'status' => ApprovalRequest::STATUS_CANCELLED,
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
