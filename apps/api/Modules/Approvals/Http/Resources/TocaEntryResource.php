<?php

namespace Modules\Approvals\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Approvals\Models\TocaEntry;

/**
 * @mixin TocaEntry
 */
class TocaEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'userId' => $this->user_id,
            'userName' => $this->user?->name,
            'subjectType' => $this->subject_type,
            'minAmount' => $this->min_amount,
            'maxAmount' => $this->max_amount,
        ];
    }
}
