<?php

namespace Modules\EPurchase\Services;

/**
 * Resolves the single display name for an upstream supplier/vendor row.
 * Prefer the English name; fall back to Khmer when English is missing.
 */
final class SupplierName
{
    /**
     * @param  array<string, mixed>  $row
     */
    public static function resolve(array $row): string
    {
        $english = trim((string) ($row['name_en'] ?? ''));

        if ($english !== '') {
            return $english;
        }

        return trim((string) ($row['name_kh'] ?? ''));
    }
}
