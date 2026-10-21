@extends('layouts.guest')
@section('title', 'DepEd Bohol RMS | Recruitment Opportunities')
@section('navTitle', 'DepEd Bohol RMS')
@section('css')
<style>
.rms-public-home{max-width:1170px;margin:0 auto}
.rms-home-hero{background:linear-gradient(118deg,#193e68,#286991);border-radius:16px;color:#fff;padding:39px 36px;box-shadow:0 10px 24px rgba(22,58,92,.14)}
.rms-home-eyebrow{font-size:.73rem;text-transform:uppercase;letter-spacing:.12em;font-weight:700;color:#c9e1f3}
.rms-home-hero h1{font-size:2rem;font-weight:700;line-height:1.25;margin:11px 0}
.rms-home-hero p{color:#e3eff7;font-size:1.05rem;max-width:740px;line-height:1.55}
.rms-home-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}
.rms-home-actions .btn{border-radius:8px;font-weight:600;min-height:44px;display:inline-flex;align-items:center}
.rms-home-actions .btn-outline-light{border-color:#c7dbeb}
.rms-home-panel{background:#fff;border:1px solid #e3eaf1;border-radius:13px;margin-top:23px;overflow:hidden;box-shadow:0 3px 15px rgba(24,53,83,.04)}
.rms-home-panel-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:20px 23px;border-bottom:1px solid #edf1f4}
.rms-home-panel-head h2{color:#283f54;font-size:1.15rem;font-weight:700;margin:0}
.rms-home-panel-head p{color:#65788b;font-size:.86rem;margin:5px 0 0}
.rms-home-panel-body{padding:19px 23px}
.rms-home-open-list{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px}
.rms-home-open-card{border:1px solid #dfe8f0;border-radius:10px;padding:19px;min-width:0;display:flex;flex-direction:column}
.rms-home-open-card h3{font-size:1.05rem;font-weight:700;color:#2c4961;line-height:1.4;margin:7px 0}
.rms-home-open-card .meta{color:#63778c;font-size:.83rem;line-height:1.75;margin-bottom:17px}
.rms-home-open-card .open-badge{background:#e7f6ed;color:#1b7243;border-radius:30px;padding:4px 10px;font-size:.73rem;font-weight:700;align-self:flex-start}
.rms-home-open-card .btn{margin-top:auto;align-self:flex-start;border-radius:7px}
.rms-home-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.rms-home-step{padding:6px 3px}
.rms-home-step .step-number{background:#eaf3fa;border-radius:10px;color:#275c89;width:41px;height:41px;display:grid;place-items:center;font-size:1.08rem;font-weight:700;margin-bottom:13px}
.rms-home-step h3{font-size:1rem;color:#30495e;font-weight:700;margin-bottom:6px}
.rms-home-step p{color:#66798b;font-size:.87rem;margin:0;line-height:1.5}
.rms-home-staff-footer{display:flex;justify-content:flex-end;gap:7px;align-items:center;flex-wrap:wrap;padding:16px 3px 12px;font-size:.9rem;color:#64788a}
.rms-home-staff-footer a{font-weight:600}
@media(min-width:768px) and (max-width:1100px){.rms-home-open-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767px){.rms-home-hero{padding:27px 21px}.rms-home-hero h1{font-size:1.5rem}.rms-home-panel-head,.rms-home-panel-body{padding:17px}.rms-home-open-list,.rms-home-steps{grid-template-columns:1fr}.rms-home-actions .btn{flex:1 1 auto;justify-content:center}}
</style>
@endsection
@section('main')
<div class="container py-4 py-lg-5 rms-public-home">
    <header class="rms-home-hero">
        <div class="rms-home-eyebrow">Department of Education · Division of Bohol</div>
        <h1>Recruitment opportunities, in one place.</h1>
        <p>Explore available positions, review requirements, and submit or monitor your applications through the DepEd Bohol Recruitment Management System.</p>
        <div class="rms-home-actions">
            <a class="btn btn-light" href="{{ route('guest.vacancies.index') }}"><i class="fas fa-briefcase mr-2" aria-hidden="true"></i>Browse Vacancies</a>
            @auth
                <a class="btn btn-outline-light" href="{{ route('applicant.dashboard') }}"><i class="fas fa-tachometer-alt mr-2" aria-hidden="true"></i>Go to My Dashboard</a>
            @else
                <a class="btn btn-outline-light" href="{{ route('applicant.login') }}"><i class="fas fa-sign-in-alt mr-2" aria-hidden="true"></i>Applicant Sign In</a>
            @endauth
        </div>
    </header>

    <section class="rms-home-panel" aria-labelledby="rmsHomeVacancies">
        <div class="rms-home-panel-head">
            <div>
                <h2 id="rmsHomeVacancies">Latest Open Vacancies</h2>
                <p>Positions accepting applications under current recruitment cycles.</p>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('guest.vacancies.index') }}">View All Vacancies <i class="fas fa-arrow-right ml-1" aria-hidden="true"></i></a>
        </div>
        <div class="rms-home-panel-body">
            @if($featuredVacancies->isNotEmpty())
                <div class="rms-home-open-list">
                    @foreach($featuredVacancies as $vacancy)
                        <article class="rms-home-open-card">
                            <span class="open-badge">Accepting Applications</span>
                            <h3>{{ $vacancy->position_title }}</h3>
                            <div class="meta">
                                <div>Recruitment Cycle: {{ $vacancy->cycle }}</div>
                                <div>Salary Grade: {{ $vacancy->salary_grade }}</div>
                                <div>Office: {{ $vacancy->getOffice() }}</div>
                            </div>
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('guest.vacancies.show', $vacancy) }}">View Position Details</a>
                        </article>
                    @endforeach
                </div>
            @else
                <p class="text-muted mb-0" role="status">No recruitment vacancies are currently open. New opportunities will appear here when available.</p>
            @endif
        </div>
    </section>

    <section class="rms-home-panel" aria-labelledby="rmsHomeHowItWorks">
        <div class="rms-home-panel-head">
            <div><h2 id="rmsHomeHowItWorks">How to Apply</h2><p>Three simple steps to start your recruitment journey.</p></div>
        </div>
        <div class="rms-home-panel-body">
            <div class="rms-home-steps">
                <div class="rms-home-step"><div class="step-number">1</div><h3>Explore open vacancies</h3><p>Find a position and review the posted qualifications and requirements.</p></div>
                <div class="rms-home-step"><div class="step-number">2</div><h3>Sign in and complete your profile</h3><p>Use your Google account and provide your personal and contact information.</p></div>
                <div class="rms-home-step"><div class="step-number">3</div><h3>Review and submit</h3><p>Confirm your application and return to your dashboard to access its details and updates.</p></div>
            </div>
        </div>
    </section>
    <footer class="rms-home-staff-footer">
        <span>DepEd Bohol staff?</span>
        <a href="{{ route('login') }}"><i class="fas fa-user-shield mr-1" aria-hidden="true"></i>Staff Login</a>
    </footer>
</div>
@endsection
