<?php

namespace Modules\EmployeeProfiles\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\EmployeeProfiles\Models\EmployeeProfile;

/**
 * @mixin EmployeeProfile
 */
class EmployeeProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'cardId' => $this->card_id,
            'position' => $this->position,
            'campusId' => $this->campus_id,
            'campusName' => $this->whenLoaded('campus', fn () => $this->campus?->name),
            'divisionId' => $this->division_id,
            'divisionName' => $this->whenLoaded('division', fn () => $this->division?->name),
            'departmentId' => $this->department_id,
            'departmentName' => $this->whenLoaded('department', fn () => $this->department?->name),
            'effectiveFrom' => $this->effective_from?->format('Y-m-d'),
            'effectiveTo' => $this->effective_to?->format('Y-m-d'),
            'isCurrent' => $this->is_current,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
