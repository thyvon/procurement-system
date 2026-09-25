<?php

namespace Modules\Approvals\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Approvals\Models\ApprovalSetting;

/**
 * @mixin ApprovalSetting
 */
class ApprovalSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'subjectType' => $this->subject_type,
            'name' => $this->name,
            'isActive' => $this->is_active,
        ];
    }
}
