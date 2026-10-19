@extends('layouts.guest')
@section('title', 'RMS | Recruitment Vacancies')
@section('navTitle', 'DepEd Bohol RMS')
@section('css')
<style>
.rms-vacancies{max-width:1170px;margin:0 auto}
.rms-vacancies-hero{background:linear-gradient(118deg,#193e68,#286991);border-radius:16px;color:#fff;padding:30px 32px;box-shadow:0 10px 24px rgba(22,58,92,.14)}
.rms-vacancies-hero .eyebrow{color:#c9e1f3;text-transform:uppercase;letter-spacing:.13em;font-size:.72rem;font-weight:700}
.rms-vacancies-hero h1{font-size:1.7rem;font-weight:700;margin:6px 0}
.rms-vacancies-hero p{color:#e3eff7;margin:0}
.rms-vacancy-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:22px}
.rms-vacancy-actions .btn{border-radius:7px;font-weight:600;min-height:41px;display:inline-flex;align-items:center}
.rms-vacancy-actions .btn-outline-light{border-color:#c7dbeb}
.rms-vacancies-list-panel{background:#fff;border:1px solid #e3eaf1;border-radius:13px;box-shadow:0 3px 15px rgba(24,53,83,.04);margin:23px 0;overflow:hidden}
.rms-vacancies-list-head{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;padding:19px 22px;border-bottom:1px solid #edf1f4}
.rms-vacancies-list-head h2{font-size:1.08rem;font-weight:700;color:#283f54;margin:0}
.rms-vacancies-list-head p{color:#64778b;font-size:.84rem;margin:5px 0 0}
.rms-vacancy-search{width:min(100%,340px);position:relative}
.rms-vacancy-search input{min-height:43px;padding-left:39px;border-radius:8px}
.rms-vacancy-search i{position:absolute;left:13px;top:14px;color:#6b7d8c}
.rms-vacancy-list{padding:8px 22px}
.rms-vacancy-entry{display:flex;justify-content:space-between;gap:18px;align-items:center;padding:19px 0;border-bottom:1px solid #eef1f5}
.rms-vacancy-entry:last-child{border-bottom:0}
.rms-vacancy-entry[hidden]{display:none!important}
.rms-vacancy-info{flex:1;min-width:0}
.rms-vacancy-info h3{font-size:1.02rem;font-weight:700;margin:0 0 8px;color:#294761}
.rms-vacancy-meta{display:flex;flex-wrap:wrap;gap:9px 17px;color:#65788b;font-size:.85rem}
.rms-vacancy-tag{display:inline-block;margin-bottom:9px;font-size:.73rem;font-weight:700;padding:4px 10px;border-radius:30px;background:#e6f6ed;color:#1d7849}
.rms-vacancy-entry-actions{display:flex;flex-wrap:wrap;gap:8px}
.rms-vacancy-entry-actions .btn{min-width:115px;border-radius:7px}
.rms-vacancy-empty{background:#f4f7fa;border-radius:9px;margin:16px 22px;padding:24px;color:#61758a}
@media(max-width:650px){.rms-vacancies-hero{padding:24px 19px}.rms-vacancies-list-head,.rms-vacancy-list{padding:16px}.rms-vacancy-entry{align-items:stretch;flex-direction:column}.rms-vacancy-entry-actions .btn{flex:1}.rms-vacancy-empty{margin:16px}}
</style>
@endsection

@section('main')
<div class="container py-4 py-lg-5 rms-vacancies">
    <header class="rms-vacancies-hero">
        <div class="eyebrow">DepEd Bohol | Recruitment Management System</div>
        <h1>Available Vacancies</h1>
        <p>Explore open recruitment opportunities and review their requirements before applying.</p>
        <nav class="rms-vacancy-actions" aria-label="Vacancy navigation">
            @auth
                <a class="btn btn-light" href="{{ route('applicant.dashboard') }}"><i class="fas fa-arrow-left mr-2" aria-hidden="true"></i>Back to Dashboard</a>
                <a class="btn btn-outline-light" href="{{ route('applicant.dashboard') }}#applicationsTitle"><i class="fas fa-clipboard-list mr-2" aria-hidden="true"></i>My Applications</a>
                <a class="btn btn-outline-light" href="{{ route('applicant.profile.edit') }}"><i class="fas fa-user-edit mr-2" aria-hidden="true"></i>My Profile</a>
            @else
                <a class="btn btn-light" href="{{ route('applicant.login') }}"><i class="fas fa-sign-in-alt mr-2" aria-hidden="true"></i>Applicant Sign In</a>
            @endauth
        </nav>
    </header>

    <section class="rms-vacancies-list-panel" aria-labelledby="rmsVacancyListTitle">
        <div class="rms-vacancies-list-head">
            <div>
                <h2 id="rmsVacancyListTitle">Open Opportunities</h2>
                <p><span id="vacancyVisibleCount">{{ $vacancies->count() }}</span> vacancies currently listed</p>
            </div>
            @if($vacancies->isNotEmpty())
                <div class="rms-vacancy-search">
                    <label class="sr-only" for="vacancySearch">Search vacancies by position or recruitment cycle</label>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input id="vacancySearch" type="search" class="form-control" placeholder="Search position or cycle…" autocomplete="off">
                </div>
            @endif
        </div>
        @if($vacancies->isNotEmpty())
            <div class="rms-vacancy-list" id="rmsVacancyList">
                @foreach($vacancies as $vacancy)
                    <article class="rms-vacancy-entry" data-search="{{ mb_strtolower($vacancy->position_title.' '.$vacancy->cycle.' '.$vacancy->getOffice()) }}">
                        <div class="rms-vacancy-info">
                            <span class="rms-vacancy-tag"><i class="fas fa-circle mr-1" aria-hidden="true"></i>Open for Applications</span>
                            <h3>{{ $vacancy->position_title }}</h3>
                            <div class="rms-vacancy-meta">
                                <span><i class="far fa-calendar mr-1" aria-hidden="true"></i>Cycle {{ $vacancy->cycle }}</span>
                                <span><i class="fas fa-building mr-1" aria-hidden="true"></i>{{ $vacancy->getOffice() }}</span>
                                <span><i class="fas fa-layer-group mr-1" aria-hidden="true"></i>Salary Grade {{ $vacancy->salary_grade }}</span>
                                <span><i class="fas fa-users mr-1" aria-hidden="true"></i>{{ $vacancy->vacancy }} {{ (int)$vacancy->vacancy === 1 ? 'vacancy' : 'vacancies' }}</span>
                            </div>
                        </div>
                        <div class="rms-vacancy-entry-actions">
                            <a class="btn btn-outline-primary" href="{{ route('guest.vacancies.show', $vacancy) }}">View Details</a>
                            <a class="btn btn-primary" href="{{ route('guest.vacancies.apply', $vacancy) }}"><i class="fas fa-file-signature mr-1" aria-hidden="true"></i>Apply</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <div id="vacancyNoMatches" class="rms-vacancy-empty d-none" role="status">No positions match your search. Try another position or recruitment cycle.</div>
        @else
            <div class="rms-vacancy-empty" role="status">There are no open recruitment opportunities at the moment. Please check again when a new cycle is announced.</div>
        @endif
    </section>
</div>
@endsection

@section('js')
<script>
(function(){
    'use strict';
    var search=document.getElementById('vacancySearch');
    if(!search)return;
    var entries=Array.from(document.querySelectorAll('#rmsVacancyList .rms-vacancy-entry'));
    var count=document.getElementById('vacancyVisibleCount');
    var empty=document.getElementById('vacancyNoMatches');
    search.addEventListener('input',function(){
        var term=search.value.trim().toLocaleLowerCase();
        var shown=0;
        entries.forEach(function(entry){
            var matches=(entry.dataset.search||'').indexOf(term)!==-1;
            entry.hidden=!matches;
            if(matches)shown++;
        });
        count.textContent=String(shown);
        empty.classList.toggle('d-none',shown!==0);
    });
})();
</script>
@endsection
