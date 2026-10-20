<?php

namespace Modules\Products\Services;

use Illuminate\Support\Str;

class CodeGenerationService
{
    /**
     * Generate the next sequential entity-scoped code, e.g. PRD-26-001.
     *
     * @param  class-string  $modelClass  Soft-delete aware model with `code` and `entity_id` columns
     */
    public function next(string $prefix, string $modelClass, ?string $entityId): string
    {
        $year = date('y');
        $sequence = 1;

        do {
            $code = $prefix.'-'.$year.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while ($modelClass::withTrashed()
            ->where('entity_id', $entityId)
            ->where('code', $code)
            ->exists());

        return $code;
    }

    /**
     * Generate the next sequential campus-aware code, e.g. PC-CEN-202606-0001.
     * The campus segment is the uppercased first 3 characters of the campus and
     * is omitted when the campus is blank, e.g. PC-202606-0001.
     *
     * @param  class-string  $modelClass  Soft-delete aware model with `code` and `entity_id` columns
     */
    public function nextWithCampus(string $prefix, string $modelClass, ?string $entityId, ?string $campus, ?\DateTimeInterface $date = null): string
    {
        $prefix = Str::upper($prefix);
        $campus = $campus === null ? '' : trim($campus);
        $campusSegment = $campus !== '' ? Str::upper(Str::substr($campus, 0, 3)) : null;
        $dateSegment = ($date ?? now())->format('Ym');
        $sequence = 1;

        do {
            $parts = [$prefix];
            if ($campusSegment !== null) {
                $parts[] = $campusSegment;
            }
            $parts[] = $dateSegment;
            $parts[] = str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $code = implode('-', $parts);
            $sequence++;
        } while ($modelClass::withTrashed()
            ->where('entity_id', $entityId)
            ->where('code', $code)
            ->exists());

        return $code;
    }

    /**
     * Derive a collision-free short code from a label, e.g. "Main Campus" -> "MAIN CAMP".
     * Suffixes "-2", "-3"… are appended when the label is already taken in the entity,
     * because a unique index rejects a bare label-derived duplicate.
     *
     * @param  class-string  $modelClass  Soft-delete aware model with `short_code` and `entity_id` columns
     */
    public function nextShortCode(string $base, string $modelClass, ?string $entityId): string
    {
        $seed = Str::upper(Str::substr($base, 0, 10));
        $sequence = 1;

        do {
            $shortCode = $sequence === 1 ? $seed : $seed.'-'.$sequence;
            $sequence++;
        } while ($modelClass::withTrashed()
            ->where('entity_id', $entityId)
            ->where('short_code', $shortCode)
            ->exists());

        return $shortCode;
    }
}
