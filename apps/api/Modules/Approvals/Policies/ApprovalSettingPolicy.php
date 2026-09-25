<?php

namespace Modules\Approvals\Policies;

use App\Models\User;
use Modules\Approvals\Models\ApprovalSetting;

/**
 * Approval settings are read-only configuration (seeded per subject type);
 * only the flows and TOCA rows beneath them are managed, so this policy
 * exposes read abilities only.
 */
class ApprovalSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('approvals.view', 'sanctum');
    }

    public function view(User $user, ?ApprovalSetting $setting = null): bool
    {
        return $user->can('approvals.view', 'sanctum');
    }
}
