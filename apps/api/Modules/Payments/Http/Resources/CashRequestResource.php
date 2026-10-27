<?php

namespace Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Modules\Invoices\Models\Invoice;
use Modules\Payments\Models\CashRequest;

/**
 * @mixin CashRequest
 */
class CashRequestResource extends JsonResource
{
    /**
     * `usedAmountUsd` is the sum of the non-cancelled invoices drawing on
     * this request; `remainingAmountUsd` is what is left of `totalUsd`
     * (negative when pre-existing data over-allocated before the cap).
     *
     * @return array{
     *     id: string,
     *     code: string,
     *     type: string,
     *     status: string,
     *     campus: string,
     *     division: string,
     *     department: string,
     *     requestDate: string|null,
     *     exchangeRate: float,
     *     modeOfPayment: string|null,
     *     paymentTo: string|null,
     *     bankAccountNo: string|null,
     *     bankName: string|null,
     *     advanceRequestFor: string|null,
     *     totalUsd: float,
     *     usedAmountUsd: float,
     *     remainingAmountUsd: float,
     *     remark: string|null,
     *     updatedAt: string|null,
     *     createdBy: string|null,
     *     createdByPosition: string|null,
     *     createdById: int|null,
     *     lines: MissingValue|Collection,
     * }
     */
    public function toArray(Request $request): array
    {
        $usedAmountUsd = $this->resolveUsedAmountUsd();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'status' => $this->status,
            'campus' => $this->campus,
            'division' => $this->division,
            'department' => $this->department,
            'requestDate' => $this->request_date?->format('Y-m-d'),
            'exchangeRate' => (float) $this->exchange_rate,
            'modeOfPayment' => $this->mode_of_payment,
            'paymentTo' => $this->payment_to,
            'bankAccountNo' => $this->bank_account_no,
            'bankName' => $this->bank_name,
            'advanceRequestFor' => $this->advance_request_for,
            'totalUsd' => (float) $this->total_usd,
            'usedAmountUsd' => $usedAmountUsd,
            'remainingAmountUsd' => round((float) $this->total_usd - $usedAmountUsd, 2),
            'remark' => $this->remark,
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i'),
            'createdBy' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'createdByPosition' => $this->whenLoaded('creator', fn () => $this->creator?->currentEmployeeProfile?->position),
            'createdById' => $this->created_by,
            'lines' => $this->relationLoaded('lines')
                ? CashRequestLineResource::collection($this->lines)
                : new MissingValue,
        ];
    }

    /**
     * Index rows carry a `withSum` aggregate (no N+1); single-row responses
     * fall back to one constrained sum query. The aggregate key can hold
     * null for a request with no invoices — that means zero, not "unknown",
     * so the fallback only runs when the key is absent entirely.
     */
    private function resolveUsedAmountUsd(): float
    {
        $attributes = $this->resource->getAttributes();

        if (array_key_exists('used_amount_usd', $attributes)) {
            return (float) $attributes['used_amount_usd'];
        }

        return (float) $this->resource->invoices()
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->sum('grand_total_usd');
    }
}
