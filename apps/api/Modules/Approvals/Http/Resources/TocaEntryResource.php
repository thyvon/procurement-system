<?php

namespace Modules\Approvals\Http\Resources;

use App\Models\User;
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
            'name' => $this->name,
            'subjectType' => $this->subject_type,
            'stepKey' => $this->step_key,
            'minAmount' => $this->min_amount,
            'maxAmount' => $this->max_amount,
            'users' => $this->usersPayload(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function usersPayload(): array
    {
        if (! $this->relationLoaded('users')) {
            return [];
        }

        return $this->users
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->values()
            ->all();
    }
}
