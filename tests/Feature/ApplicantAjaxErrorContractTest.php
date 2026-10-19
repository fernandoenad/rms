<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApplicantAjaxErrorContractTest extends TestCase
{
    public function test_ajax_http_401_returns_json_instead_of_legacy_html_error_page(): void
    {
        Route::get('/__test_applicant_http_401', function () {
            abort(401, 'Authentication has expired.');
        });

        $this->getJson('/__test_applicant_http_401')
            ->assertStatus(401)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('message', 'Authentication has expired.');
    }

    public function test_ajax_http_404_returns_json_for_missing_resources(): void
    {
        Route::get('/__test_applicant_http_404', function () {
            abort(404);
        });

        $this->getJson('/__test_applicant_http_404')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure(['message']);
    }

    public function test_non_json_http_401_keeps_legacy_html_error_page(): void
    {
        Route::get('/__test_applicant_html_401', function () {
            abort(401);
        });

        $this->get('/__test_applicant_html_401')
            ->assertStatus(401)
            ->assertHeader('content-type', 'text/html; charset=UTF-8');
    }

    public function test_expired_applicant_json_route_never_returns_staff_login_html(): void
    {
        $this->postJson(route('applicant.claims.store'), [
            'application_code' => 'EXPIRED-SESSION-TEST',
            'email' => 'applicant@example.test',
        ])->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure(['message']);

        $this->postJson(route('guest.assessments.attempts.answer', ['attempt' => 999999]), [
            'written_exam_id' => 1,
            'selected_option_id' => 1,
        ])->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');
    }
}
