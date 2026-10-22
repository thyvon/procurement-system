<?php

namespace Modules\Approvals\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Modules\Approvals\Models\ApprovalAction;
use Modules\Approvals\Models\ApprovalRequest;

/**
 * @mixin ApprovalRequest
 */
class ApprovalRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $steps = $this->snapshot['steps'] ?? [];
        $currentStep = collect($steps)->firstWhere('position', $this->current_position);

        return [
            'id' => $this->getKey(),
            'subjectType' => $this->subject_type,
            'subjectId' => $this->subject_id,
            'status' => $this->status,
            'documentCode' => $this->snapshot['documentCode'] ?? null,
            'amountSnapshot' => $this->amount_snapshot,
            'flow' => $this->snapshot['flow'] ?? null,
            'currentStep' => $currentStep === null ? null : [
                'position' => $currentStep['position'],
                'key' => $currentStep['key'],
                'label' => $currentStep['label'],
                'assigneeId' => $currentStep['assigneeId'] ?? null,
                'assigneeName' => $currentStep['assigneeName'] ?? null,
                'assigneePosition' => $currentStep['assigneePosition'] ?? null,
                'assigneeSignature' => $this->decidedSignature($currentStep),
                'signers' => $this->stepSigners($currentStep),
            ],
            'steps' => array_map(function (array $step): array {
                $step['assigneeSignature'] = $this->decidedSignature($step);
                $step['signers'] = $this->stepSigners($step);

                return $step;
            }, $steps),
            'submittedBy' => $this->creator?->name,
            'submittedAt' => $this->created_at?->format('Y-m-d H:i'),
            'decidedAt' => $this->decided_at?->format('Y-m-d H:i'),
            'createdById' => $this->created_by,
            'document' => $this->snapshot['document'] ?? null,
            'actions' => ApprovalActionResource::collection($this->whenLoaded('actions')),
        ];
    }

    /**
     * Every responsible user of a step (co-signed steps list several), with
     * `actedAt` marking who has decided — read from the pivot when it is
     * eager-loaded, null otherwise. Steps frozen before co-signing expose
     * their single legacy assignee as a one-entry list.
     *
     * @param  array<string, mixed>  $step
     * @return array<int, array{id: int|null, name: string|null, position: string|null, actedAt: string|null}>
     */
    private function stepSigners(array $step): array
    {
        $signers = collect($step['assignees'] ?? [])
            ->map(fn (array $signer): array => [
                'id' => isset($signer['id']) ? (int) $signer['id'] : null,
                'name' => $signer['name'] ?? null,
                'position' => $signer['position'] ?? null,
                'actedAt' => null,
            ]);

        if ($signers->isEmpty() && ($step['assigneeName'] ?? null) !== null) {
            $signers = collect([
                [
                    'id' => isset($step['assigneeId']) ? (int) $step['assigneeId'] : null,
                    'name' => $step['assigneeName'],
                    'position' => $step['assigneePosition'] ?? null,
                    'actedAt' => null,
                ],
            ]);
        }

        if ($signers->isEmpty() || ! $this->relationLoaded('stepAssignees')) {
            return $signers->values()->all();
        }

        $position = (int) ($step['position'] ?? 0);
        $actedByUser = $this->stepAssignees
            ->where('step_position', $position)
            ->whereNotNull('acted_at')
            ->keyBy('user_id');

        return $signers
            ->map(function (array $signer) use ($actedByUser): array {
                $row = $signer['id'] !== null ? $actedByUser->get($signer['id']) : null;
                $signer['actedAt'] = $row?->acted_at?->format('Y-m-d H:i');

                return $signer;
            })
            ->values()
            ->all();
    }

    /**
     * Snapshots freeze the signature path at submit time; resolve it to an
     * absolute URL per response (APP_URL is environment-specific). Steps
     * written before this field existed simply map to null.
     *
     * Scramble cannot infer Storage::url()'s return type, which collapses the
     * ternary to a null-only union; the tag pins the real contract instead.
     *
     * @scramble-return string|null
     */
    private function signatureUrl(mixed $path): ?string
    {
        return is_string($path) && $path !== ''
            ? Storage::disk('public')->url($path)
            : null;
    }

    /**
     * Snapshots freeze every assignee's signature at submit time, but a
     * signature only becomes official once its approver has acted on the step
     * (`approve`/`reject`/`return`, or the automatic `record` stamp; a
     * `cancel` is the submitter withdrawing, not a decision). Pending steps —
     * including `currentStep` — therefore expose null.
     *
     * Reads the already-loaded `actions` relation only: list endpoints that
     * skip it hide every signature rather than lazy-loading per row.
     *
     * @param  array<string, mixed>  $step
     *
     * @scramble-return string|null
     */
    private function decidedSignature(array $step): ?string
    {
        $position = $step['position'] ?? null;

        if ($position === null || ! $this->relationLoaded('actions')) {
            return null;
        }

        $decided = $this->actions->contains(function (ApprovalAction $action) use ($position): bool {
            return $action->action !== 'cancel'
                && (int) $action->step_position === (int) $position;
        });

        return $decided ? $this->signatureUrl($step['assigneeSignature'] ?? null) : null;
    }
}
