<?php

namespace Tests\Unit;

use App\Models\Assessment;
use App\Models\Template;
use App\Models\Vacancy;
use App\Support\RqaScoreEligibility;
use PHPUnit\Framework\TestCase;

class RqaScoreEligibilityTest extends TestCase
{
    private function vacancy(string $type): Vacancy
    {
        $vacancy = new Vacancy();
        $vacancy->setRelation('template', new Template(['type' => $type]));
        return $vacancy;
    }

    public function test_non_teaching_and_teaching_related_categories_include_below_fifty(): void
    {
        foreach ([
            'Non-Teaching', 'non teaching', 'Teaching-Related',
            'Teaching-Related-Admin', 'Teaching Related Administrator',
            'School Administrator', 'Teaching-related/School Administrator',
        ] as $category) {
            $vacancy = $this->vacancy($category);
            $this->assertTrue(RqaScoreEligibility::includesBelowFifty($vacancy), $category);
            $query = RqaScoreEligibility::apply(Assessment::query()->where('assessments.status',3), $vacancy);
            $this->assertStringNotContainsString('score', $query->toSql(), $category);
        }
    }

    public function test_regular_teaching_and_unclassified_template_keep_existing_cutoff(): void
    {
        foreach (['Teaching', 'Elementary Teaching', 'Kindergarten', 'SPIMS', '', 'Teacher VII'] as $category) {
            $vacancy = $this->vacancy($category);
            $this->assertFalse(RqaScoreEligibility::includesBelowFifty($vacancy), $category);
            $query = RqaScoreEligibility::apply(Assessment::query()->where('assessments.status',3), $vacancy);
            $this->assertStringContainsString('score', $query->toSql(), $category);
            $this->assertContains(50, $query->getBindings());
        }
    }
}
