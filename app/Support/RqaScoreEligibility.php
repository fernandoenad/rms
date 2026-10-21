<?php

namespace App\Support;

use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Recruitment category controls display cutoff; completion status is
 * unchanged and no score is ever rewritten.
 */
final class RqaScoreEligibility
{
    public static function includesBelowFifty(Vacancy $vacancy): bool
    {
        $type = mb_strtolower(trim((string) $vacancy->template?->type));
        $normalized = preg_replace('/[^a-z0-9]+/u', ' ', $type);
        $normalized = trim(preg_replace('/\s+/', ' ', (string) $normalized));
        // Recognize template categories, not arbitrary position titles.
        // An ordinary classroom-teaching template must retain the cutoff.
        return preg_match('/\\bnon teaching\\b|\\bnonteaching\\b|\\bteaching related\\b|\\bteachingrelated\\b|\\bschool administrator\\b|\\bsg\\b/', $normalized) === 1;
    }

    public static function apply(Builder $query, Vacancy $vacancy): Builder
    {
        return self::includesBelowFifty($vacancy)
            ? $query
            : $query->where('assessments.score', '>=', 50);
    }
}
