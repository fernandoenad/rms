{{-- Consistent navigation for authenticated applicant journeys, without touching
     public guest visits, staff screens, or unrelated recruitment reports. --}}
@auth
@php
    $applicantPage = request()->routeIs(
        'applicant.dashboard',
        'applicant.profile.*',
        'applicant.apply.*',
        'guest.vacancies.index',
        'guest.vacancies.show',
        'guest.applications.show',
        'guest.assessments.attempts.take',
        'guest.skills.attempts.take'
    );
    $isDashboard = request()->routeIs('applicant.dashboard');
    $isAssessment = request()->routeIs('guest.assessments.attempts.take', 'guest.skills.attempts.take');
    $vacancy = request()->route('vacancy');
    $application = request()->route('application');
    $attempt = request()->route('attempt');

    // Never use untrusted HTTP Referer or user-provided return URLs as a back destination.
    // These are named, server-controlled routes only.
    $backUrl = route('applicant.dashboard');
    $backLabel = 'Back to Dashboard';
    if (request()->routeIs('guest.vacancies.show')) {
        $backUrl = route('guest.vacancies.index');
        $backLabel = 'Back to Vacancies';
    } elseif (request()->routeIs('applicant.apply.review') && $vacancy) {
        $backUrl = route('guest.vacancies.show', $vacancy);
        $backLabel = 'Back to Vacancy';
    } elseif ($isAssessment && $attempt && $attempt->application_id) {
        $backUrl = route('guest.applications.show', $attempt->application_id);
        $backLabel = 'Back to Application';
    }

    $pageLabel = 'Applicant Portal';
    if (request()->routeIs('applicant.profile.*')) {
        $pageLabel = 'Master Profile';
    } elseif (request()->routeIs('applicant.apply.*')) {
        $pageLabel = 'Application Review';
    } elseif (request()->routeIs('guest.vacancies.index', 'guest.vacancies.show')) {
        $pageLabel = 'Vacancies';
    } elseif (request()->routeIs('guest.applications.show')) {
        $pageLabel = 'Application Details';
    } elseif ($isAssessment) {
        $pageLabel = 'Assessment';
    }
@endphp
@if($applicantPage && !$isDashboard)
    <nav class="applicant-quick-nav" aria-label="Applicant page navigation">
        <div class="container applicant-quick-nav-inner">
            <div class="applicant-quick-nav-links">
                <a class="btn btn-sm btn-outline-secondary applicant-quick-back"
                   href="{{ $backUrl }}" aria-label="{{ $backLabel }}">
                    <i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>{{ $backLabel }}
                </a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('applicant.dashboard') }}">
                    <i class="fas fa-tachometer-alt mr-1" aria-hidden="true"></i>Dashboard
                </a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('guest.vacancies.index') }}">
                    <i class="fas fa-briefcase mr-1" aria-hidden="true"></i>Vacancies
                </a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('applicant.dashboard') }}#applicationsTitle">
                    <i class="fas fa-clipboard-list mr-1" aria-hidden="true"></i>My Applications
                </a>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('applicant.profile.edit') }}">
                    <i class="fas fa-user-edit mr-1" aria-hidden="true"></i>My Profile
                </a>
            </div>
            <span class="applicant-quick-current" aria-current="page">{{ $pageLabel }}</span>
        </div>
        @if($isAssessment)
            <div class="container applicant-quick-assessment-notice" role="note">
                <i class="fas fa-exclamation-triangle mr-1" aria-hidden="true"></i>
                Leaving an ongoing assessment may not submit your answers. Use the assessment's Submit control when you are finished.
            </div>
        @endif
    </nav>
@endif
@endauth
