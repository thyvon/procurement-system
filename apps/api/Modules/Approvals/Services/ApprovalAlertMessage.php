<?php

namespace Modules\Approvals\Services;

/**
 * Single source of truth for approval alert wording. Every transport
 * (Telegram now, email/SMS/webhook later) renders from these strings —
 * notifications must never hardcode their own copy. Context keys are the
 * ones ApprovalService passes into the notification constructors.
 *
 * Output is Telegram HTML (parse_mode=HTML) in the plain modern-bot style:
 * a bold headline, a blank line, then one icon-labelled field per line.
 * Every interpolated value runs through e() so a code or name can never
 * emit a tag Telegram rejects with 400.
 */
final class ApprovalAlertMessage
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function actionRequired(array $context): string
    {
        return implode("\n\n", [
            '<b>🔔 Approval action required</b>',
            implode("\n", [
                '📄 Document: '.self::document($context),
                '💵 Amount: '.e((string) ($context['amount'] ?? '-')).' USD',
                '📋 Step: '.e((string) ($context['stepLabel'] ?? '-')),
                '🆔 Request: <code>'.e((string) ($context['requestId'] ?? '-')).'</code>',
            ]),
            '👉 Review this request in the app.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function result(array $context): string
    {
        $status = (string) ($context['status'] ?? 'updated');

        return implode("\n\n", [
            '<b>'.self::statusIcon($status).' Approval result: '.e($status).'</b>',
            implode("\n", [
                '📄 Document: '.self::document($context),
                '💵 Amount: '.e((string) ($context['amount'] ?? '-')).' USD',
                '🆔 Request: <code>'.e((string) ($context['requestId'] ?? '-')).'</code>',
            ]),
        ]);
    }

    /**
     * Document code when the round froze one, otherwise the subject type —
     * a draft preview may reference no code yet. Escaped once here so both
     * messages stay safe.
     *
     * @param  array<string, mixed>  $context
     */
    private static function document(array $context): string
    {
        $code = $context['documentCode'] ?? null;

        if (is_string($code) && $code !== '') {
            return e($code);
        }

        return e((string) ($context['subjectType'] ?? 'document'));
    }

    private static function statusIcon(string $status): string
    {
        return match ($status) {
            'approved' => '✅',
            'rejected' => '❌',
            'returned' => '↩️',
            'cancelled' => '🚫',
            default => '🔄',
        };
    }
}
