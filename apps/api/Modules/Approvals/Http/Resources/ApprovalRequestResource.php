<?php

namespace Modules\Approvals\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
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
            ],
            'steps' => $steps,
            'submittedBy' => $this->creator?->name,
            'submittedAt' => $this->created_at?->format('Y-m-d H:i'),
            'decidedAt' => $this->decided_at?->format('Y-m-d H:i'),
            'actions' => ApprovalActionResource::collection($this->whenLoaded('actions')),
        ];
    }
}
