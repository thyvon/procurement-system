<?php

namespace App\Support;

/**
 * Watermark label for a printed document that is not yet finally approved:
 * only an approved (or cleared) subject is an official compliance document,
 * so every other status must print visibly marked as not official. One shared
 * rule for all print sheets — the services pass `watermark` into the view and
 * print/layout.blade.php renders it.
 */
final class PrintWatermark
{
    /** Statuses whose printout is an official, usable compliance document. */
    private const OFFICIAL = ['approved', 'cleared'];

    /**
     * Status-specific bilingual label (English + Khmer), or null when the
     * document is official. An unknown or missing status is not official —
     * fail safe and watermark it.
     *
     * @return array{en: string, kh: string}|null
     */
    public static function for(?string $status): ?array
    {
        if ($status !== null && in_array($status, self::OFFICIAL, true)) {
            return null;
        }

        return self::LABELS[$status]
            ?? ['en' => 'NOT APPROVED', 'kh' => 'មិនទាន់អនុម័ត'];
    }

    /**
     * @var array<string, array{en: string, kh: string}>
     */
    private const LABELS = [
        'draft' => ['en' => 'DRAFT', 'kh' => 'ព្រាង'],
        'pending' => ['en' => 'PENDING', 'kh' => 'រង់ចាំ'],
        'in_review' => ['en' => 'IN REVIEW', 'kh' => 'កំពុងត្រួតពិនិត្យ'],
        'rejected' => ['en' => 'REJECTED', 'kh' => 'បានបដិសេធ'],
        'returned' => ['en' => 'RETURNED', 'kh' => 'បានបញ្ជូនមកវិញ'],
        'cancelled' => ['en' => 'CANCELLED', 'kh' => 'បានលុបចោល'],
    ];
}
