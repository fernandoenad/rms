@extends('layouts.guest')
@section('title', 'RMS | Vacancy Details')
@section('navTitle', 'DepEd Bohol RMS')
@section('css')
<style>
.rms-vacancy-detail{max-width:1170px;margin:0 auto}
.rms-vacancy-hero{background:linear-gradient(118deg,#193e68,#286991);color:white;border-radius:16px;padding:30px 32px;box-shadow:0 10px 24px rgba(22,58,92,.14)}
.rms-vacancy-hero .eyebrow{text-transform:uppercase;letter-spacing:.13em;font-size:.72rem;font-weight:700;color:#c9e1f3}
.rms-vacancy-hero h1{font-size:1.7rem;font-weight:700;margin:6px 0 8px}
.rms-vacancy-hero p{color:#e3eff7;margin:0}
.rms-vacancy-hero-actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:22px}
.rms-vacancy-hero-actions .btn{border-radius:7px;font-weight:600;min-height:41px;display:inline-flex;align-items:center}
.rms-vacancy-hero-actions .btn-outline-light{border-color:#c7dbeb}
.rms-vacancy-panel{background:#fff;border:1px solid #e3eaf1;border-radius:13px;box-shadow:0 3px 15px rgba(24,53,83,.04);margin:22px 0;overflow:hidden}
.rms-vacancy-panel-heading{display:flex;align-items:center;gap:12px;padding:19px 22px;border-bottom:1px solid #edf1f4}
.rms-vacancy-panel-icon{height:39px;width:39px;border-radius:10px;background:#edf4fa;color:#27628c;display:grid;place-items:center}
.rms-vacancy-panel-heading h2{font-size:1.08rem;font-weight:700;margin:0;color:#283f54}
.rms-vacancy-panel-body{padding:20px 22px}
.rms-vacancy-overview{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:19px}
.rms-vacancy-overview dt{color:#687b8e;font-size:.79rem;font-weight:500;margin-bottom:5px}
.rms-vacancy-overview dd{color:#30495e;font-weight:700;font-size:.99rem;margin:0;overflow-wrap:anywhere}
.rms-vacancy-requirements{color:#30495e;white-space:normal;overflow-wrap:anywhere;line-height:1.65}
.rms-vacancy-next{background:#f1f6fa;color:#436178;padding:15px;border-left:3px solid #427ba7;border-radius:7px;margin-bottom:16px}
.rms-vacancy-cta{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.rms-vacancy-cta .btn{border-radius:7px;min-width:145px}
@media(max-width:650px){.rms-vacancy-hero{padding:24px 19px}.rms-vacancy-panel-heading,.rms-vacancy-panel-body{padding:16px}.rms-vacancy-overview{grid-template-columns:1fr}.rms-vacancy-cta .btn{flex:1}}
</style>
@endsection

@section('main')
<div class="container py-4 py-lg-5 rms-vacancy-detail">
    <header class="rms-vacancy-hero">
        <div class="eyebrow">DepEd Bohol | Recruitment Management System</div>
        <h1>{{ $vacancy->position_title }}</h1>
        <p>Recruitment cycle {{ $vacancy->cycle }} &middot; Review the requirements and vacancy details before proceeding.</p>
        <nav class="rms-vacancy-hero-actions" aria-label="Vacancy details navigation">
            <a class="btn btn-light" href="{{ route('guest.vacancies.index') }}"><i class="fas fa-arrow-left mr-2" aria-hidden="true"></i>Back to Vacancies</a>
            @auth
                <a class="btn btn-outline-light" href="{{ route('applicant.dashboard') }}"><i class="fas fa-tachometer-alt mr-2" aria-hidden="true"></i>Dashboard</a>
                <a class="btn btn-outline-light" href="{{ route('applicant.profile.edit') }}"><i class="fas fa-user-edit mr-2" aria-hidden="true"></i>My Profile</a>
            @else
                <a class="btn btn-outline-light" href="{{ route('applicant.login') }}">Applicant Sign In</a>
            @endauth
        </nav>
    </header>
    <section class="rms-vacancy-panel" aria-labelledby="rmsVacancyOverview">
        <div class="rms-vacancy-panel-heading">
            <span class="rms-vacancy-panel-icon"><i class="fas fa-briefcase" aria-hidden="true"></i></span>
            <h2 id="rmsVacancyOverview">Vacancy Overview</h2>
        </div>
        <div class="rms-vacancy-panel-body">
            <dl class="rms-vacancy-overview">
                <div><dt>Position</dt><dd>{{ $vacancy->position_title }}</dd></div>
                <div><dt>Recruitment Cycle</dt><dd>{{ $vacancy->cycle }}</dd></div>
                <div><dt>Salary Grade</dt><dd>{{ $vacancy->salary_grade }}</dd></div>
                <div><dt>Base Pay</dt><dd>₱{{ number_format($vacancy->base_pay, 2) }}</dd></div>
                <div><dt>Office</dt><dd>{{ $vacancy->getOffice() }}</dd></div>
                <div><dt>Number of Vacancies</dt><dd>{{ $vacancy->vacancy }}</dd></div>
            </dl>
        </div>
    </section>
    <section class="rms-vacancy-panel" aria-labelledby="rmsVacancyQualifications">
        <div class="rms-vacancy-panel-heading">
            <span class="rms-vacancy-panel-icon"><i class="fas fa-clipboard-check" aria-hidden="true"></i></span>
            <h2 id="rmsVacancyQualifications">Qualifications and Requirements</h2>
        </div>
        <div class="rms-vacancy-panel-body">
            <div class="rms-vacancy-requirements">{!! nl2br(e($vacancy->qualifications ?? 'Not provided.')) !!}</div>
        </div>
    </section>
    <section class="rms-vacancy-panel" aria-labelledby="rmsVacancyNextSteps">
        <div class="rms-vacancy-panel-heading">
            <span class="rms-vacancy-panel-icon"><i class="fas fa-file-signature" aria-hidden="true"></i></span>
            <h2 id="rmsVacancyNextSteps">Next Steps</h2>
        </div>
        <div class="rms-vacancy-panel-body">
            <div class="rms-vacancy-next">Check your qualifications and confirm your profile details before sending an application.</div>
            <div class="rms-vacancy-cta">
                @if($vacancy->isOpenForApplications())
                    <a class="btn btn-primary" href="{{ route('guest.vacancies.apply', $vacancy) }}"><i class="fas fa-file-signature mr-2" aria-hidden="true"></i>Apply for Position</a>
                @else
                    <span class="badge badge-secondary p-2">Applications Closed</span>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('guest.vacancies.index') }}">Back to Vacancies</a>
            </div>
        </div>
    </section>
</div>
@endsection
