<?php

namespace Modules\Approvals\Services;

/**
 * Single source of truth for approval alert wording. Every transport
 * (Telegram now, email/SMS/webhook later) renders from these strings —
 * notifications must never hardcode their own copy. Context keys are the
 * ones ApprovalService passes into the notification constructors.
 */
final class ApprovalAlertMessage
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function actionRequired(array $context): string
    {
        return implode("\n", [
            'Approval action required',
            'Document: '.self::document($context),
            'Amount: '.($context['amount'] ?? '-').' USD',
            'Step: '.($context['stepLabel'] ?? '-'),
            'Request: '.($context['requestId'] ?? '-'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function result(array $context): string
    {
        return implode("\n", [
            'Approval result: '.($context['status'] ?? 'updated'),
            'Document: '.self::document($context),
            'Amount: '.($context['amount'] ?? '-').' USD',
            'Request: '.($context['requestId'] ?? '-'),
        ]);
    }

    /**
     * Document code when the round froze one, otherwise the subject type —
     * a draft preview may reference no code yet.
     *
     * @param  array<string, mixed>  $context
     */
    private static function document(array $context): string
    {
        $code = $context['documentCode'] ?? null;

        if (is_string($code) && $code !== '') {
            return $code;
        }

        return (string) ($context['subjectType'] ?? 'document');
    }
}
