<?php

namespace Modules\Approvals\Services;

use App\Models\User;
use Modules\Approvals\Models\TocaEntry;

/**
 * Assigns authority entries to a user (replace-all, mirroring
 * UserRoleService::syncRoles). The relation is built inline so the core
 * User model never has to import a module class.
 */
class TocaEntryService
{
    /**
     * @param  array<int, string>  $tocaEntryIds
     */
    public function syncUserEntries(User $user, array $tocaEntryIds): void
    {
        $user->belongsToMany(
            TocaEntry::class,
            'toca_entry_user',
            'user_id',
            'toca_entry_id',
        )->sync($tocaEntryIds);
    }
}
