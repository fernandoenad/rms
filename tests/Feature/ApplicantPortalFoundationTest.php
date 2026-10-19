<?php

namespace Tests\Feature;

use App\Models\ApplicantProfile;
use App\Models\ApplicantApplicationLink;
use App\Models\Application;
use App\Models\User;
use App\Services\Applicant\ApplicantProfileToApplicationMapper;
use App\Services\Applicant\HistoricalApplicationLinkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicantPortalFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ownership_is_kept_outside_applications_table(): void
    {
        $this->assertTrue(Schema::hasTable('applicant_application_links'));
        $this->assertFalse(Schema::hasColumn('applications', 'applicant_profile_id'));
    }

    public function test_mapper_copies_only_legacy_personal_fields(): void
    {
        $user = User::factory()->create();
        $profile = ApplicantProfile::create([
            'user_id' => $user->id,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.com',
        ]);
        $mapped = app(ApplicantProfileToApplicationMapper::class)->map($profile);
        $this->assertSame('Maria', $mapped['first_name']);
        $this->assertSame('maria@example.com', $mapped['email']);
        $this->assertArrayNotHasKey('user_id', $mapped);
    }
}
