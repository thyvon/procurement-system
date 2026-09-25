<?php

namespace Modules\Approvals\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Approvals\Models\ApprovalFlow;

/**
 * @mixin ApprovalFlow
 */
class ApprovalFlowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'code' => $this->code,
            'name' => $this->name,
            'minAmount' => $this->min_amount,
            'maxAmount' => $this->max_amount,
            'isActive' => $this->is_active,
            'setting' => new ApprovalSettingResource($this->whenLoaded('setting')),
            'steps' => ApprovalStepResource::collection($this->whenLoaded('steps')),
        ];
    }
}
