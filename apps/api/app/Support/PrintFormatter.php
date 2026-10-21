<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Display formatting for the server-rendered print sheets — the exact twin of
 * the browser helpers the sheets used (`formatMoney` / `formatDate` /
 * `dateOnly` in apps/web) so a sheet rendered by the API prints exactly what
 * the screen used to.
 */
final class PrintFormatter
{
    /**
     * `formatMoney(amount, currency)`: en-US grouping, 2 decimals for USD and
     * 0 for KHR, the currency symbol followed by a non-breaking space; a
     * leading minus is hoisted in front of the symbol.
     */
    public static function money(float $amount, string $currency = 'USD'): string
    {
        $isKhr = $currency === 'KHR';
        $formatted = number_format($amount, $isKhr ? 0 : 2, '.', ',');
        $symbol = $isKhr ? '៛' : '$';

        if (str_starts_with($formatted, '-')) {
            return '-'.$symbol."\u{00A0}".substr($formatted, 1);
        }

        return $symbol."\u{00A0}".$formatted;
    }

    /**
     * The sheets' `dateOnly()`: keep the date part of a `Y-m-d H:i` snapshot
     * and render `MMM dd, yyyy`; empty or unparseable input renders empty.
     */
    public static function dateOnly(?string $value): string
    {
        return self::parse($value)?->format('M d, Y') ?? '';
    }

    /**
     * The cash request meta rows' `formatDate()`: `dd MMM yyyy`, `-` when
     * there is no date.
     */
    public static function date(?string $value): string
    {
        return self::parse($value)?->format('d M Y') ?? '-';
    }

    /**
     * `String(x)` on a numeric column: cast through float so a `decimal`
     * like "4100.00" reads "4100", matching the browser sheet.
     */
    public static function numeric(float|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) (float) $value;
    }

    /**
     * The sheets' `x || "—"`: empty or missing text renders an em dash
     * ("0" is a value and renders as-is, as it does in the browser).
     */
    public static function orDash(?string $value): string
    {
        return ($value ?? '') === '' ? '—' : $value;
    }

    private static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
