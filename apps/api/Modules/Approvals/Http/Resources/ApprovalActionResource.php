<?php

namespace Modules\Approvals\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Approvals\Models\ApprovalAction;

/**
 * @mixin ApprovalAction
 */
class ApprovalActionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->getKey(),
            'stepPosition' => $this->step_position,
            'stepKey' => $this->step_key,
            'action' => $this->action,
            'comment' => $this->comment,
            'actor' => [
                'id' => $this->actor?->getKey(),
                'name' => $this->actor?->name,
            ],
            'actedAt' => $this->acted_at?->format('Y-m-d H:i'),
        ];
    }
}
