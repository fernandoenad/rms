<?php

namespace App\Support;

/** Official internal CAR/RQA only. Never mutate the assessment JSON remarks. */
final class RqaIntegrityRemarks
{
    public static function format(?string $original, bool $confirmed): string
    {
        $remarks = trim((string) $original);
        if (!$confirmed) {
            return $remarks;
        }
        // Avoid appending a second marker if an old remark already ended in one.
        return preg_match('/\\*$/u', $remarks) ? $remarks : trim($remarks.' *');
    }
}
