<?php

namespace Modules\Payments\Services;

use App\Models\User;
use App\Support\Currency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payments\Models\CashRequest;
use Modules\Payments\Models\CashRequestLine;
use Modules\Products\Services\CodeGenerationService;

/**
 * Owns every write on a cash request: the server-generated campus code, the
 * line list and the derived USD totals, and the draft → cancelled lifecycle.
 * The client may only suggest lines and header inputs; totals are always
 * recomputed here so the stored document is internally consistent.
 */
class CashRequestService
{
    /**
     * Allowed status moves. `cancelled` is terminal, and only `draft` /
     * `returned` documents can still change — the approval engine owns every
     * other transition.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        CashRequest::STATUS_DRAFT => [CashRequest::STATUS_CANCELLED],
        CashRequest::STATUS_CANCELLED => [],
    ];

    /**
     * Code prefix per request type: petty cash → PC, advance → AD, cash → CR.
     *
     * @var array<string, string>
     */
    private const PREFIXES = [
        CashRequest::TYPE_PETTY_CASH => 'PC',
        CashRequest::TYPE_ADVANCE => 'AD',
        CashRequest::TYPE_CASH => 'CR',
    ];

    public function __construct(private readonly CodeGenerationService $codes) {}

    /**
     * @param  array<string, mixed>  $data  from cashRequestData()
     */
    public function create(array $data, User $user): CashRequest
    {
        return DB::transaction(function () use ($data, $user): CashRequest {
            $cashRequest = CashRequest::create([
                'entity_id' => $user->entity_id,
                'code' => $this->codes->nextWithCampus(
                    self::PREFIXES[$data['type']],
                    CashRequest::class,
                    $user->entity_id,
                    $data['campus'],
                    Carbon::parse($data['request_date']),
                ),
                'type' => $data['type'],
                'status' => CashRequest::STATUS_DRAFT,
                'campus' => $data['campus'],
                'division' => $data['division'],
                'department' => $data['department'],
                'request_date' => $data['request_date'],
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'mode_of_payment' => $data['mode_of_payment'] ?? null,
                'payment_to' => $data['payment_to'] ?? null,
                'bank_account_no' => $data['bank_account_no'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'advance_request_for' => $data['advance_request_for'] ?? null,
                'total_usd' => 0,
                'remark' => $data['remark'] ?? null,
                'created_by' => $user->getKey(),
                'updated_by' => $user->getKey(),
            ]);

            $this->syncLines($cashRequest, $data, $user);

            return $cashRequest->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data  from cashRequestData()
     */
    public function update(CashRequest $cashRequest, array $data, User $user): CashRequest
    {
        $this->ensureEditable($cashRequest);

        return DB::transaction(function () use ($cashRequest, $data, $user): CashRequest {
            $cashRequest->update([
                'type' => $data['type'],
                'campus' => $data['campus'],
                'division' => $data['division'],
                'department' => $data['department'],
                'request_date' => $data['request_date'],
                'exchange_rate' => $data['exchange_rate'] ?? $cashRequest->exchange_rate,
                'mode_of_payment' => $data['mode_of_payment'] ?? null,
                'payment_to' => $data['payment_to'] ?? null,
                'bank_account_no' => $data['bank_account_no'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'advance_request_for' => $data['advance_request_for'] ?? null,
                'remark' => $data['remark'] ?? null,
                'updated_by' => $user->getKey(),
            ]);

            // Wholesale replace: forceDelete so re-entered lines never collide
            // with soft-deleted rows on a future unique index.
            $cashRequest->lines()->forceDelete();

            $this->syncLines($cashRequest, $data, $user);

            return $cashRequest->refresh();
        });
    }

    public function changeStatus(CashRequest $cashRequest, string $status, User $user): CashRequest
    {
        $allowed = self::TRANSITIONS[$cashRequest->status] ?? [];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "A cash request with status '{$cashRequest->status}' cannot move to '{$status}'.",
            ]);
        }

        $cashRequest->update([
            'status' => $status,
            'updated_by' => $user->getKey(),
        ]);

        return $cashRequest->refresh();
    }

    /**
     * Only drafts and returned documents are editable: once a cash request is
     * in review it belongs to the approval flow, and a decided or cancelled
     * one is closed.
     */
    public function ensureEditable(CashRequest $cashRequest): void
    {
        if (! in_array($cashRequest->status, [CashRequest::STATUS_DRAFT, CashRequest::STATUS_RETURNED], true)) {
            throw ValidationException::withMessages([
                'status' => "A cash request with status '{$cashRequest->status}' cannot be modified.",
            ]);
        }
    }

    public function ensureDeletable(CashRequest $cashRequest): void
    {
        if ($cashRequest->status !== CashRequest::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => "Only a draft cash request can be deleted, this one is '{$cashRequest->status}'.",
            ]);
        }
    }

    /**
     * The approval listener's bridge: a request whose clearance has been
     * approved reaches this terminal status.
     */
    public function markCleared(CashRequest $cashRequest): void
    {
        $cashRequest->update([
            'status' => CashRequest::STATUS_CLEARED,
        ]);
    }

    /**
     * Rewrite the line list and recompute every derived money column. Each
     * line amount converts to USD through its own currency and the header's
     * entry-time exchange rate.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncLines(CashRequest $cashRequest, array $data, User $user): void
    {
        $headerExchangeRate = (float) ($cashRequest->exchange_rate ?? 1);
        $totalUsd = 0.0;

        foreach (array_values($data['lines']) as $position => $line) {
            $amountUsd = Currency::toUsd(
                (float) $line['amount'],
                (string) ($line['currency'] ?? Currency::USD),
                $headerExchangeRate,
            );

            $totalUsd += $amountUsd;

            CashRequestLine::create([
                'entity_id' => $cashRequest->entity_id,
                'cash_request_id' => $cashRequest->getKey(),
                'position' => $position,
                'description' => $line['description'],
                'currency' => $line['currency'] ?? Currency::USD,
                'amount' => $line['amount'],
                'amount_usd' => $amountUsd,
            ]);
        }

        $cashRequest->update([
            'total_usd' => round($totalUsd, 2),
            'updated_by' => $user->getKey(),
        ]);
    }
}
