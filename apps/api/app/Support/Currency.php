<?php

namespace App\Support;

/**
 * Multi-currency helpers shared across modules.
 *
 * Documents are recorded in their own currency (USD or KHR) together with
 * the exchange rate at entry time — expressed as KHR per 1 USD (e.g. 4100).
 * Cross-document amounts (approval bands, authority limits) stay
 * USD-denominated, so non-USD amounts convert through here at the point
 * where they enter the approval engine.
 */
final class Currency
{
    public const USD = 'USD';

    public const KHR = 'KHR';

    /**
     * The currencies the system accepts. Future modules reuse this list
     * instead of hardcoding codes again.
     *
     * @var array<int, string>
     */
    public const CODES = [self::USD, self::KHR];

    /**
     * Convert a document amount to its USD equivalent.
     */
    public static function toUsd(float $amount, string $currency, float $exchangeRate): float
    {
        if ($currency !== self::KHR) {
            return round($amount, 2);
        }

        // Validation enforces exchange_rate > 0; the guard only keeps band
        // resolution from crashing on bad data.
        $rate = $exchangeRate > 0 ? $exchangeRate : 1.0;

        return round($amount / $rate, 2);
    }
}
