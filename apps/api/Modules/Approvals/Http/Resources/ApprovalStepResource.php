<?php

namespace Modules\Approvals\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Approvals\Models\ApprovalStep;

/**
 * @mixin ApprovalStep
 */
class ApprovalStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'position' => $this->position,
            'key' => $this->key,
            'label' => $this->label,
            'actionMode' => $this->action_mode,
            'allowedActions' => $this->allowed_actions,
            'showOnPrint' => $this->show_on_print,
        ];
    }
}
