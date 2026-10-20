<?php

namespace Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Collection;
use Modules\Payments\Models\CashRequest;

/**
 * @mixin CashRequest
 */
class CashRequestResource extends JsonResource
{
    /**
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
            'remark' => $this->remark,
            'updatedAt' => $this->updated_at?->format('Y-m-d H:i'),
            'createdBy' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'createdByPosition' => $this->whenLoaded('creator', fn () => $this->creator?->position),
            'createdById' => $this->created_by,
            'lines' => $this->relationLoaded('lines')
                ? CashRequestLineResource::collection($this->lines)
                : new MissingValue,
        ];
    }
}
