<?php

namespace Modules\Approvals\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Payments\Services\PaymentPrintService;
use Modules\PurchaseOrders\Services\EvaluationPrintService;
use Spatie\LaravelPdf\PdfBuilder;
use Throwable;

/**
 * Print-sheet bytes for an approval request's subject document, rendered
 * outside HTTP (queue worker) so a Telegram alert can attach the same A4
 * PDF the web's /print routes serve. Any failure — unknown subject, soft
 * deleted creator, Chrome down — logs once and returns null so the alert
 * falls back to a plain text message.
 */
class ApprovalDocumentPrinter
{
    public function __construct(
        private readonly ApprovalSubjectRegistry $registry,
        private readonly PaymentPrintService $payments,
        private readonly EvaluationPrintService $evaluations,
    ) {}

    /**
     * @return array{binary: string, filename: string}|null
     */
    public function forRequest(ApprovalRequest $request): ?array
    {
        try {
            $user = $request->creator;

            if ($user === null) {
                return null;
            }

            $definition = $this->registry->definition($request->subject_type);
            $subject = $this->registry->find($request->subject_type, $request->subject_id);
            $builder = $this->builderFor($request->subject_type, $subject, $user);

            if ($builder === null) {
                return null;
            }

            $code = (string) $subject->getAttribute($definition['code_column']);

            return [
                'binary' => $builder->generatePdfContent(),
                'filename' => ($code !== '' ? $code : $request->subject_type).'.pdf',
            ];
        } catch (Throwable $exception) {
            Log::warning('approval.alert.print_failed', [
                'approval_request_id' => $request->getKey(),
                'subject_type' => $request->subject_type,
                'subject_id' => $request->subject_id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function builderFor(string $subjectType, Model $subject, User $user): ?PdfBuilder
    {
        return match ($subjectType) {
            'evaluation' => $this->evaluations->forEvaluation($subject, $user),
            'petty_cash_request', 'advance_request', 'cash_request' => $this->payments->forCashRequest($subject, $user),
            'cash_clearance' => $this->payments->forCashClearance($subject, $user),
            'advance_liquidation' => $this->payments->forAdvanceLiquidation($subject, $user),
            default => null,
        };
    }
}
