<?php

namespace Modules\Invoices\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Invoices\Models\Invoice;

/**
 * Each invoice branch owns its own permission pair: holders of
 * `direct_purchase_invoices.view` read direct-purchase documents, holders of
 * `pro_purchase_invoices.view` read pro-purchase documents — and likewise
 * per branch for `*.manage`. The ability resolves the pair from the invoice
 * type: class abilities (`viewAny`, `create`) receive the branch type as an
 * extra authorize argument, instance abilities read `$model->type`; an
 * unknown or missing type denies. Edit/delete additionally require
 * ownership (`created_by` = the actor) — everyone manages only the documents
 * they created. Model parameters are nullable so the same policy serves both
 * instance abilities (`update($user, $model)`) and class-string abilities
 * (`update($user, $model::class)`); without a model there is no owner and no
 * type, so edit/delete deny.
 */
class InvoicePolicy
{
    public function viewAny(User $user, ?string $type = null): bool
    {
        return $this->can($user, $type, 'view');
    }

    public function view(User $user, ?Model $model = null): bool
    {
        return $this->can($user, $model?->getAttribute('type'), 'view');
    }

    public function create(User $user, ?string $type = null): bool
    {
        return $this->can($user, $type, 'manage');
    }

    public function update(User $user, ?Model $model = null): bool
    {
        return $this->canWrite($user, $model);
    }

    public function delete(User $user, ?Model $model = null): bool
    {
        return $this->canWrite($user, $model);
    }

    private function canWrite(User $user, ?Model $model): bool
    {
        return $model !== null
            && $this->can($user, $model->getAttribute('type'), 'manage')
            && $model->getAttribute('created_by') !== null
            && (int) $model->getAttribute('created_by') === (int) $user->getKey();
    }

    /**
     * Holds the `<branch>.<action>` permission for an invoice type; a missing
     * or unrecognized type has no branch, so it denies.
     */
    private function can(User $user, mixed $type, string $action): bool
    {
        $prefix = match ($type) {
            Invoice::TYPE_DIRECT_PURCHASE => 'direct_purchase_invoices',
            Invoice::TYPE_PRO_PURCHASE_INVOICE => 'pro_purchase_invoices',
            default => null,
        };

        return $prefix !== null && $user->can("{$prefix}.{$action}", 'sanctum');
    }
}
