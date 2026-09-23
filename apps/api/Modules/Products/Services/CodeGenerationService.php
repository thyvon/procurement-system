<?php

namespace Modules\Products\Services;

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
}
