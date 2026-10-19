<?php

namespace Tests\Feature;

use App\Models\ApplicantApplicationLink;
use App\Models\ApplicantProfile;
use App\Models\Application;
use App\Models\User;
use App\Services\Applicant\ApplicationOwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApplicantOwnershipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_does_not_own_an_application(): void
    {
        $application = new Application();
        $application->id = 98765;
        $request = Request::create('/applications/98765');
        $this->assertFalse(app(ApplicationOwnershipService::class)->owns($request, $application));
    }

    public function test_profile_mapper_never_modifies_an_application(): void
    {
        $profile = new ApplicantProfile([
            'first_name' => 'Updated',
            'last_name' => 'Person',
            'email' => 'person@example.com',
        ]);
        $application = new Application([
            'first_name' => 'Original',
            'last_name' => 'Person',
            'email' => 'old@example.com',
        ]);
        $mapped = app(\App\Services\Applicant\ApplicantProfileToApplicationMapper::class)->map($profile);
        $this->assertSame('Updated', $mapped['first_name']);
        $this->assertSame('Original', $application->first_name);
        $this->assertSame('old@example.com', $application->email);
    }
}
